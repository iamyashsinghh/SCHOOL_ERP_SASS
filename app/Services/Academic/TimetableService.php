<?php

namespace App\Services\Academic;

use App\Enums\Day;
use App\Http\Resources\Academic\ClassTimingResource;
use App\Http\Resources\Academic\TimetableResource;
use App\Http\Resources\Asset\Building\RoomResource;
use App\Models\Tenant\Academic\ClassTiming;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Academic\Timetable;
use App\Models\Tenant\Academic\TimetableAllocation;
use App\Models\Tenant\Academic\TimetableRecord;
use App\Models\Tenant\Asset\Building\Room;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Student\SubjectWiseStudent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    public function preRequisite(): array
    {
        $days = Day::getOptions();

        $classTimings = ClassTimingResource::collection(ClassTiming::query()
            ->with('sessions')
            ->byPeriod()
            ->get());

        $rooms = RoomResource::collection(Room::query()
            ->withFloorAndBlock()
            ->notAHostel()
            ->get());

        return compact('classTimings', 'days', 'rooms');
    }

    public function getDetail(Timetable $timetable)
    {
        $timetable->load(['batch.course', 'room' => fn ($q) => $q->withFloorAndBlock()]);

        $rooms = Room::query()
            ->withFloorAndBlock()
            ->notAHostel()
            ->get();

        $weekdays = Day::getOptions();

        $subjects = Subject::query()
            ->withSubjectRecord($timetable->batch_id, $timetable->batch->course_id)
            ->orderBy('subjects.position', 'asc')
            ->get();

        if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
            $electiveSubjects = $subjects->where('is_elective', 1);

            $students = Student::query()
                ->byPeriod()
                ->record()
                ->filterForStudentAndGuardian()
                ->get();

            $selectedElectiveSubjects = SubjectWiseStudent::query()
                ->whereIn('student_id', $students->pluck('id')->all())
                ->whereIn('subject_id', $electiveSubjects->pluck('id')->all())
                ->get();

            $subjects = $subjects->filter(function ($subject) use ($selectedElectiveSubjects) {
                return ! $subject->is_elective || $selectedElectiveSubjects->pluck('subject_id')->contains($subject->id);
            });
        }

        $timetableRecords = TimetableRecord::query()
            ->with('classTiming.sessions')
            ->where('timetable_id', $timetable->id)
            ->get();

        $timetableAllocations = TimetableAllocation::query()
            ->WhereIn('timetable_record_id', $timetableRecords->pluck('id')->all())
            ->get();

        $employees = Employee::query()
            ->with('contact')
            ->whereIn('id', $timetableAllocations->pluck('employee_id')->all())
            ->get();

        $days = [];
        foreach ($weekdays as $day) {
            $timetableRecord = $timetableRecords->firstWhere('day', Arr::get($day, 'value'));

            if (! $timetableRecord) {
                continue;
            }

            if ($timetableRecord->is_holiday) {
                $days[] = [
                    'label' => Arr::get($day, 'label'),
                    'value' => Arr::get($day, 'value'),
                    'is_holiday' => true,
                    'sessions' => [],
                ];

                continue;
            }

            $startTime = $timetableRecord->classTiming->sessions->min('start_time');
            $endTime = $timetableRecord->classTiming->sessions->max('end_time');

            $duration = Carbon::parse($startTime->value)->diff(Carbon::parse($endTime->value));

            $sessions = [];
            foreach ($timetableRecord->classTiming->sessions as $session) {
                $timetableAllocation = $timetableAllocations
                    ->where('timetable_record_id', $timetableRecord->id)
                    ->where('class_timing_session_id', $session->id);

                $allotments = [];

                foreach ($timetableAllocation as $allocation) {
                    $subject = $subjects->firstWhere('id', $allocation->subject_id);
                    $employee = $employees->firstWhere('id', $allocation->employee_id);
                    $room = $rooms->firstWhere('id', $allocation->room_id);

                    if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
                        if (! $subject) {
                            continue;
                        }
                    }

                    $allotments[] = [
                        'room' => $room?->uuid,
                        'room_name' => $room?->fullName,
                        'subject' => $subject ? [
                            'uuid' => $subject?->uuid,
                            'name' => $subject?->name,
                            'code' => $subject?->code,
                            'shortcode' => $subject?->shortcode,
                        ] : null,
                        'employee' => $employee ? [
                            'uuid' => $employee?->uuid,
                            'name' => $employee?->contact->name,
                        ] : null,
                    ];
                }

                if (empty($allotments)) {
                    $allotments[] = [
                        'room' => null,
                        'room_name' => null,
                        'subject' => null,
                        'employee' => null,
                    ];
                }

                $sessions[] = [
                    'name' => $session->name,
                    'uuid' => $session->uuid,
                    'start_time' => $session->start_time,
                    'end_time' => $session->end_time,
                    'duration' => $session->start_time->formatted.' - '.$session->end_time->formatted,
                    'is_break' => (bool) $session->is_break,
                    'allotments' => $allotments,
                ];
            }

            $days[] = [
                'label' => Arr::get($day, 'label'),
                'value' => Arr::get($day, 'value'),
                'is_holiday' => false,
                'duration' => $duration->h.' '.trans('list.durations.hours').' '.$duration->i.' '.trans('list.durations.minutes'),
                'period' => $startTime->formatted.' - '.$endTime->formatted,
                'sessions' => $sessions,
            ];
        }

        $timetable->has_detail = true;
        $timetable->days = $days;

        return TimetableResource::make($timetable);
    }

    public function create(Request $request): Timetable
    {
        \DB::beginTransaction();

        $timetable = Timetable::forceCreate($this->formatParams($request));

        $this->updateRecords($request, $timetable);

        \DB::commit();

        return $timetable;
    }

    private function formatParams(Request $request, ?Timetable $timetable = null): array
    {
        $formatted = [
            'batch_id' => $request->batch_id,
            'effective_date' => $request->effective_date,
            'room_id' => $request->room_id,
            'description' => $request->description,
        ];

        if (! $timetable) {
            //
        }

        return $formatted;
    }

    public function export(Timetable $timetable, $isBulk = false)
    {
        $timetable->load('records.allocations');

        $batch = $timetable->batch;

        $subjects = Subject::query()
            ->withSubjectRecord($batch->id, $batch->course_id)
            ->get();

        $rooms = Room::query()
            ->withFloorAndBlock()
            ->notAHostel()
            ->get();

        if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
            $electiveSubjects = $subjects->where('is_elective', 1);

            $students = Student::query()
                ->byPeriod()
                ->record()
                ->filterForStudentAndGuardian()
                ->get();

            $selectedElectiveSubjects = SubjectWiseStudent::query()
                ->whereIn('student_id', $students->pluck('id')->all())
                ->whereIn('subject_id', $electiveSubjects->pluck('id')->all())
                ->get();

            $subjects = $subjects->filter(function ($subject) use ($selectedElectiveSubjects) {
                return ! $subject->is_elective || $selectedElectiveSubjects->pluck('subject_id')->contains($subject->id);
            });
        }

        $hasSameClassTiming = $timetable->records->where('class_timing_id', '!=', null)->pluck('class_timing_id')->unique()->count() === 1;

        $classTimings = ClassTiming::query()
            ->with('sessions')
            ->whereIn('id', $timetable->records->pluck('class_timing_id')->all())
            ->byPeriod()
            ->get();

        $inchargeEmployeeIds = [];
        foreach ($timetable->records as $record) {
            $inchargeEmployeeIds = array_merge($inchargeEmployeeIds, $record->allocations->pluck('employee_id')->all());
        }

        $inchargeEmployeeIds = array_unique($inchargeEmployeeIds);

        $employees = Employee::query()
            ->with('contact')
            ->whereIn('id', $inchargeEmployeeIds)
            ->get();

        $days = [];
        $maxNoOfSessions = 0;
        foreach ($timetable->records as $record) {
            $classTiming = $classTimings->firstWhere('id', $record->class_timing_id);

            if (! $classTiming) {
                continue;
            }

            if ($maxNoOfSessions < $classTiming->sessions->count()) {
                $maxNoOfSessions = $classTiming->sessions->count();
            }

            $allocations = $record->allocations;

            $sessions = [];
            foreach ($classTiming->sessions as $session) {
                $allotments = $allocations->where('class_timing_session_id', $session->id);

                $newAllotments = [];
                foreach ($allotments as $allotment) {
                    $subject = $subjects->firstWhere('id', $allotment->subject_id);
                    $room = $rooms->firstWhere('id', $allotment->room_id);
                    $employee = $employees->firstWhere('id', $allotment->employee_id);

                    if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
                        if (! $subject) {
                            continue;
                        }
                    }

                    $newAllotments[] = [
                        'subject' => $subject,
                        'room' => $room?->full_name,
                        'employee' => $employee?->contact?->name,
                    ];
                }

                $sessions[] = [
                    'name' => $session->name,
                    'start_time' => \Cal::time($session->start_time),
                    'end_time' => \Cal::time($session->end_time),
                    'allotments' => $newAllotments,
                    'is_break' => $session->is_break,
                ];
            }

            $days[] = [
                'day' => Day::getDetail($record->day),
                'start_time' => \Cal::time($classTiming?->sessions?->min('start_time')),
                'end_time' => \Cal::time($classTiming?->sessions?->max('end_time')),
                'is_holiday' => $record->is_holiday,
                'sessions' => $sessions,
                'filler_session' => $maxNoOfSessions - count($sessions),
            ];
        }

        $timetable->has_same_class_timing = $hasSameClassTiming;
        $timetable->room_name = $rooms->firstWhere('id', $timetable->room_id)?->full_name;

        if ($isBulk) {
            return [
                'timetable' => $timetable,
                'batch' => $batch,
                'days' => $days,
            ];
        }

        return view('print.academic.timetable.index', compact('timetable', 'batch', 'days'));
    }

    public function bulkExport(Request $request)
    {
        $uuids = explode(',', $request->query('uuids', ''));

        $timetables = Timetable::query()
            ->whereIn('uuid', $uuids)
            ->get();

        $exportedTimetables = [];
        foreach ($timetables as $timetable) {
            $exportedTimetables[] = $this->export($timetable, true);
        }

        return view('print.academic.timetable.bulk', compact('exportedTimetables'));
    }

    private function updateRecords(Request $request, Timetable $timetable): void
    {
        foreach ($request->records as $record) {
            $timetableRecord = TimetableRecord::firstOrCreate([
                'timetable_id' => $timetable->id,
                'day' => Arr::get($record, 'day'),
            ]);

            $timetableRecord->is_holiday = Arr::get($record, 'is_holiday', false);
            $timetableRecord->class_timing_id = Arr::get($record, 'class_timing_id');
            $timetableRecord->save();
        }
    }

    public function update(Request $request, Timetable $timetable): void
    {
        // $timetableRecords = TimetableRecord::query()
        //     ->whereTimetableId($timetable->id)
        //     ->get();

        // if (TimetableAllocation::query()
        //     ->whereIn('timetable_record_id', $timetableRecords->pluck('id')->all())
        //     ->exists()) {
        //     throw ValidationException::withMessages(['message' => trans('academic.timetable.could_not_modify_if_allocated')]);
        // }

        \DB::beginTransaction();

        $timetable->forceFill($this->formatParams($request, $timetable))->save();

        $this->updateRecords($request, $timetable);

        \DB::commit();
    }

    public function deletable(Timetable $timetable, $validate = false): ?bool
    {
        $timetableRecords = TimetableRecord::query()
            ->whereTimetableId($timetable->id)
            ->get();

        if (TimetableAllocation::query()
            ->whereIn('timetable_record_id', $timetableRecords->pluck('id')->all())
            ->where(function ($q) {
                $q->whereNotNull('room_id')
                    ->orWhereNotNull('employee_id')
                    ->orWhereNotNull('subject_id');
            })
            ->exists()) {
            throw ValidationException::withMessages(['message' => trans('academic.timetable.could_not_modify_if_allocated')]);
        }

        return true;
    }
}
