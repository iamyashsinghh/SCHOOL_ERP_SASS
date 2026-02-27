<?php

namespace App\Services\Academic;

use App\Enums\Day;
use App\Models\Academic\Batch;
use App\Models\Academic\ClassTimingSession;
use App\Models\Academic\Subject;
use App\Models\Academic\Timetable;
use App\Models\Academic\TimetableAllocation;
use App\Models\Asset\Building\Room;
use App\Models\Employee\Employee;
use App\Models\Incharge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TeacherTimetableService
{
    public function export(Request $request)
    {
        // abort('398', trans('general.errors.feature_under_development'));

        $layout = $request->query('layout', 'grouped');
        $employees = Str::toArray($request->query('employees'));

        if (! in_array($layout, ['default'])) {
            $layout = 'default';
        }

        if (empty($employees)) {
            $employee = Employee::query()
                ->auth()
                ->first();

            $employees = Employee::query()
                ->summary()
                ->filterAccessible()
                ->where('employees.uuid', $employee->uuid)
                ->get();
        } else {
            $employees = Employee::query()
                ->summary()
                ->filterAccessible()
                ->whereIn('employees.uuid', $employees)
                ->get();
        }

        if ($employees->isEmpty()) {
            abort(398, trans('global.could_not_find', ['attribute' => trans('employee.employee')]));
        }

        $data = [];
        foreach ($employees as $employee) {
            $days = $this->getData($employee);

            $days = collect($days)->map(function ($day) {
                $sessions = collect(Arr::get($day, 'sessions', []))->map(function ($session) {
                    return [
                        'name' => Arr::get($session, 'name'),
                        'session_id' => Arr::get($session, 'id'),
                        'start_time_formatted' => Arr::get($session, 'start_time')?->formatted,
                        'start_time' => Arr::get($session, 'start_time')?->at,
                        'end_time_formatted' => Arr::get($session, 'end_time')?->formatted,
                        'end_time' => Arr::get($session, 'end_time')?->at,
                        'subject' => Arr::get($session, 'subject.name'),
                        'subject_code' => Arr::get($session, 'subject.code'),
                        'room' => Arr::get($session, 'room'),
                        'batch' => Arr::get($session, 'batch'),
                    ];
                })->sortBy('start_time')->toArray();

                return [
                    'day' => Arr::get($day, 'day.label'),
                    'day_number' => Arr::get($day, 'day.number'),
                    'class_timing_id' => Arr::get($day, 'class_timing_id'),
                    'class_timing_sessions' => Arr::get($day, 'class_timing_sessions'),
                    'sessions' => $sessions,
                ];
            })
            // ->filter(function ($item) {
            //     return $item['day_number'] != 6;
            // })
                ->sortBy('day_number')->toArray();

            // return response()->json($days);

            $data[] = $this->prepareData($employee, $days, $layout);
        }

        return view('print.academic.timetable.teacher.'.$layout, compact('data'));
    }

    private function prepareData(Employee $employee, array $days, ?string $layout = null)
    {
        return $this->getDefaultLayoutData($days, $employee);
    }

    private function getDefaultLayoutData(array $days, Employee $employee)
    {
        $timetable = $days;

        $timeSlots = collect($timetable)
            ->pluck('sessions')
            ->flatten(1)
            ->map(function ($session) {
                return [
                    'start' => $session['start_time'],
                    'end' => $session['end_time'],
                ];
            })
            ->unique(function ($slot) {
                return $slot['start'].'-'.$slot['end'];
            })
            ->sortBy('start')
            ->values();

        return [
            'employee' => $employee,
            'timetable' => $timetable,
            'timeSlots' => $timeSlots,
        ];
    }

    private function getData(Employee $employee)
    {
        $subjectIncharges = Incharge::query()
            ->where('employee_id', $employee->id)
            ->where('model_type', 'Subject')
            // ->where('detail_type', 'Batch') // To allow subject incharge without batch & course
            ->get();

        $rooms = Room::query()
            ->withFloorAndBlock()
            ->notAHostel()
            ->get();

        $subjects = Subject::query()
            ->whereIn('id', $subjectIncharges->pluck('model_id')->all())
            ->get();

        $allowedBatches = $subjectIncharges->pluck('detail_id')->all();

        $timetableAllocationBatchIds = TimetableAllocation::query()
            ->select('timetables.batch_id')
            ->join('timetable_records', 'timetable_allocations.timetable_record_id', '=', 'timetable_records.id')
            ->join('timetables', 'timetable_records.timetable_id', '=', 'timetables.id')
            ->where('timetable_allocations.employee_id', $employee->id)
            ->get();

        $allowedBatches = array_merge($allowedBatches, $timetableAllocationBatchIds->pluck('batch_id')->all());

        $batches = Batch::query()
            ->with('course')
            ->byPeriod()
            ->whereIn('id', $allowedBatches)
            // ->whereIn('id', $subjectIncharges->pluck('detail_id')->all()) // To allow subject incharge without batch & course
            ->get();

        $timetables = Timetable::query()
            ->whereIn('batch_id', $batches->pluck('id')->all())
            ->get();

        $latestTimetableId = [];
        foreach ($batches as $batch) {
            $timetable = $timetables->where('batch_id', $batch->id)
                ->where('effective_date.value', '<=', today()->toDateString())
                ->sortByDesc('effective_date.value')
                ->first();

            $latestTimetableId[] = $timetable?->id;
        }

        $AllDayTimetableAllocations = TimetableAllocation::query()
            ->select('timetable_allocations.*', 'timetables.batch_id', 'timetable_records.day', 'timetable_records.class_timing_id')
            ->join('timetable_records', 'timetable_allocations.timetable_record_id', '=', 'timetable_records.id')
            ->join('timetables', 'timetable_records.timetable_id', '=', 'timetables.id')
            ->whereIn('timetable_records.timetable_id', $latestTimetableId)
            ->whereIn('subject_id', $subjects->pluck('id')->all())
            ->where('employee_id', $employee->id)
            ->get();

        $dayWiseTimetableAllocations = $AllDayTimetableAllocations->groupBy('day');

        $classTimingSessions = ClassTimingSession::query()
            ->whereIn('class_timing_id', $AllDayTimetableAllocations->pluck('class_timing_id')->all())
            ->get();

        $days = [];
        foreach ($dayWiseTimetableAllocations as $key => $timetableAllocations) {
            $sessions = [];
            foreach ($timetableAllocations as $timetableAllocation) {
                $classTimingSession = $classTimingSessions->where('id', $timetableAllocation->class_timing_session_id)->first();

                $subject = $subjects->where('id', $timetableAllocation->subject_id)->first();

                $room = $rooms->where('id', $timetableAllocation->room_id)->first();

                $batch = $batches->where('id', $timetableAllocation->batch_id)->first();

                $sessions[] = [
                    'name' => $classTimingSession->name,
                    'id' => $classTimingSession->id,
                    'start_time' => $classTimingSession->start_time,
                    'end_time' => $classTimingSession->end_time,
                    'subject' => [
                        'name' => $subject?->name,
                        'code' => $subject?->code,
                    ],
                    'room' => $room?->full_name,
                    'batch' => $batch?->course->name.' '.$batch?->name,
                ];
            }

            $dayName = $timetableAllocation->day;
            $dayValue = Day::tryFrom($dayName);
            $dayDetail = Day::getDetail($dayValue);
            $dayDetail['number'] = $dayValue->getNumberValue();

            $days[] = [
                'day' => $dayDetail,
                'class_timing_id' => $timetableAllocation->class_timing_id,
                'class_timing_sessions' => $classTimingSessions->where('class_timing_id', $timetableAllocation->class_timing_id)->map(function ($s) {
                    return [
                        'start_time' => $s->start_time?->at,
                        'end_time' => $s->end_time?->at,
                        'start_time_formatted' => $s->start_time?->formatted,
                        'end_time_formatted' => $s->end_time?->formatted,
                    ];
                })->toArray(),
                'sessions' => $sessions,
            ];
        }

        return $days;
    }
}
