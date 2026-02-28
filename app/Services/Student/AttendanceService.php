<?php

namespace App\Services\Student;

use App\Actions\Student\FetchBatchWiseStudent;
use App\Enums\OptionType;
use App\Enums\Student\AttendanceSession;
use App\Jobs\Notifications\Student\SendBatchAttendanceNotification;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Calendar\Holiday;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function preRequisite(Request $request)
    {
        $types = [
            ['code' => 'P', 'label' => trans('student.attendance.types.present'), 'color' => 'bg-success'],
            ['code' => 'A', 'label' => trans('student.attendance.types.absent'), 'color' => 'bg-danger'],
            // ['code' => 'L', 'label' => trans('student.attendance.types.late'), 'color' => 'bg-warning'],
            // ['code' => 'HD', 'label' => trans('student.attendance.types.half_day'), 'color' => 'bg-info'],
            // ['code' => 'EL', 'label' => trans('student.attendance.types.early_leaving'), 'color' => 'bg-primary'],
        ];
        $sessions = AttendanceSession::getOptions();

        $attendanceTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get();

        foreach ($attendanceTypes as $type) {
            $types[] = [
                'code' => $type->getMeta('code'),
                'label' => $type->name,
                'color' => $type->getMeta('color', 'bg-secondary'),
            ];
        }

        $methods = [
            ['label' => trans('student.attendance.batch_wise'), 'value' => 'batch_wise'],
            ['label' => trans('student.attendance.subject_wise'), 'value' => 'subject_wise'],
        ];

        $batches = Batch::getList();

        return compact('methods', 'types', 'sessions', 'batches');
    }

    private function validateInput(Request $request): Batch
    {
        $forSubject = $request->method == 'subject_wise' ? true : false;

        return Batch::query()
            ->byPeriod()
            ->filterAccessible($forSubject)
            ->whereUuid($request->batch)
            ->getOrFail(trans('academic.batch.batch'), 'batch');
    }

    public function store(Request $request)
    {
        $request->validate([
            'method' => 'required|in:batch_wise,subject_wise',
            'date' => 'required|date_format:Y-m-d',
            'batch' => 'required|uuid',
            'subject' => 'required_if:method,subject_wise|uuid',
            'session' => 'required_if:method,subject_wise|in:'.implode(',', AttendanceSession::getKeys()),
        ]);

        $batch = $this->validateInput($request);

        $isDefault = true;
        $subject = null;
        if ($request->method == 'subject_wise') {
            $isDefault = false;
            $request->merge([
                'for_subject' => true,
            ]);
            $subject = Subject::query()
                ->withSubjectRecord($batch->id, $batch->course_id)
                ->where('subjects.uuid', $request->subject)
                ->getOrFail(trans('academic.subject.subject'), 'subject');
        }

        $attendancePastDayLimit = config('config.student.attendance_past_day_limit', 0) + 1;
        $pastDateLimit = today()->subDays($attendancePastDayLimit)->endOfDay();

        if (Carbon::parse($request->date)->lt($pastDateLimit)) {
            throw ValidationException::withMessages(['message' => trans('student.attendance.could_not_mark_past_date', ['attribute' => $attendancePastDayLimit])]);
        }

        if (Carbon::parse($request->date)->gt(today())) {
            throw ValidationException::withMessages(['message' => trans('student.attendance.could_not_mark_in_future')]);
        }

        $session = AttendanceSession::tryFrom($request->session) ?? AttendanceSession::FIRST;

        $holiday = Holiday::query()
            ->byPeriod()
            ->where('start_date', '<=', $request->date)
            ->where('end_date', '>=', $request->date)
            ->first();

        // let them mark attendance forcefully if holiday
        // if ($holiday) {
        //     throw ValidationException::withMessages(['date' => trans('student.attendance.could_not_mark_if_holiday')]);
        // }

        if (! $holiday && $request->boolean('mark_as_holiday')) {
            $request->validate([
                'holiday_reason' => 'required',
            ]);

            $attendance = Attendance::firstOrCreate([
                'batch_id' => $batch->id,
                'subject_id' => $subject?->id,
                'date' => $request->date,
                'session' => $session,
            ]);
            $attendance->values = [];
            $attendance->is_default = $isDefault;
            $attendance->setMeta([
                'is_holiday' => true,
                'holiday_reason' => $request->holiday_reason,
            ]);
            $attendance->save();

            return;
        }

        $request->merge([
            'on_date' => $request->date,
            'select_all' => true,
        ]);

        $students = (new FetchBatchWiseStudent)->execute($request->all(), true);
        $filteredStudentUuids = Arr::pluck($students, 'uuid');

        // Filtering student in later stage
        // if (array_diff(Arr::pluck($request->students, 'uuid'), Arr::pluck($students, 'uuid'))) {
        //     throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        // }

        $day = Carbon::parse($request->date)->format('j');

        $students = $request->students;

        $availableTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get()
            ->map(fn ($type) => strtoupper($type->getMeta('code')))
            ->values()
            ->toArray();

        array_unshift($availableTypes, 'P', 'A');

        $data = [];
        foreach ($students as $student) {
            $attendance = Arr::get($student, 'attendance');

            if (in_array(Arr::get($student, 'uuid'), $filteredStudentUuids)) {
                if (! empty($attendance) && in_array($attendance, $availableTypes)) {
                    $data[$attendance][] = Arr::get($student, 'uuid');
                }
            }

            // Can be used when date wise attendance is required
            // $attendances = Arr::get($student, 'attendances', []);

            // $attendance = collect($attendances)->filter(function($value, $key) use ($day) {
            //     return $key == '_'.$day;
            // })->first();

            // if (Arr::get($attendance, 'code')) {
            //     $data[Arr::get($attendance, 'code')][] = Arr::get($student, 'uuid');
            // }
        }

        $attendance = Attendance::firstOrCreate([
            'batch_id' => $batch->id,
            'subject_id' => $subject?->id,
            'date' => $request->date,
            'session' => $session,
        ]);
        $attendance->values = collect($data)->transform(function ($value, $key) {
            return [
                'code' => $key,
                'uuids' => $value,
            ];
        })->values()->all();

        $attendance->is_default = $isDefault;
        $attendance->save();
    }

    public function remove(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $batch = $this->validateInput($request);

        $subject = null;
        if ($request->method == 'subject_wise') {
            $request->merge([
                'for_subject' => true,
            ]);
            $subject = Subject::query()
                ->withSubjectRecord($batch->id, $batch->course_id)
                ->where('subjects.uuid', $request->subject)
                ->getOrFail(trans('academic.subject.subject'), 'subject');
        }

        $session = AttendanceSession::tryFrom($request->session) ?? AttendanceSession::FIRST;

        $attendance = Attendance::query()
            ->whereBatchId($batch->id)
            ->whereSession($session)
            ->whereSubjectId($subject?->id)
            ->where('date', $request->date)
            ->first();

        if ($attendance) {
            $attendance->delete();
        }
    }

    public function sendNotification(Request $request)
    {
        $request->validate([
            'method' => 'required|in:batch_wise,subject_wise',
            'date' => 'required|date_format:Y-m-d',
            'batch' => 'required|uuid',
            'subject' => 'required_if:method,subject_wise|uuid',
            'session' => 'required_if:method,subject_wise|in:'.implode(',', AttendanceSession::getKeys()),
        ]);

        $batch = $this->validateInput($request);

        $subject = null;
        if ($request->method == 'subject_wise') {
            $request->merge([
                'for_subject' => true,
            ]);
            $subject = Subject::query()
                ->withSubjectRecord($batch->id, $batch->course_id)
                ->where('subjects.uuid', $request->subject)
                ->getOrFail(trans('academic.subject.subject'), 'subject');
        }

        $session = AttendanceSession::tryFrom($request->session) ?? AttendanceSession::FIRST;

        $attendance = Attendance::query()
            ->whereBatchId($batch->id)
            ->whereSession($session)
            ->whereSubjectId($subject?->id)
            ->where('date', $request->date)
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $attendance->validateSent();

        SendBatchAttendanceNotification::dispatch([
            'attendance_id' => $attendance->id,
            'sender_user_id' => auth()->id(),
            'team_id' => auth()->user()->current_team_id,
        ]);

        $attendance->markNotificationAsSent();
    }
}
