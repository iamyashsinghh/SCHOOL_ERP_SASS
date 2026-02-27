<?php

namespace App\Jobs\Notifications\Student;

use App\Concerns\SetConfigForJob;
use App\Enums\OptionType;
use App\Models\Option;
use App\Models\Student\Attendance;
use App\Models\Student\Student;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Throwable;

class SendBatchAttendanceNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'student']);

        $attendance = Attendance::query()
            ->findOrFail(Arr::get($this->params, 'attendance_id'));

        $availableCodes = [
            ['code' => 'P', 'label' => trans('student.attendance.types.present')],
            ['code' => 'A', 'label' => trans('student.attendance.types.absent')],
            // ['code' => 'L', 'label' => trans('student.attendance.types.late')],
            // ['code' => 'HD', 'label' => trans('student.attendance.types.half_day')],
            // ['code' => 'EL', 'label' => trans('student.attendance.types.early_leaving')],
        ];

        $attendanceTypes = Option::query()
            ->byTeam($teamId)
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get();

        foreach ($attendanceTypes as $type) {
            $availableCodes[] = [
                'code' => $type->getMeta('code'),
                'label' => $type->name,
            ];
        }

        $jobs = [];

        Student::query()
            ->summary($teamId)
            ->where('students.batch_id', $attendance->batch_id)
            ->where(function ($q) use ($attendance) {
                $q->whereNull('students.end_date')
                    ->orWhere('students.end_date', '>=', $attendance->date->value);
            })
            ->chunk(100, function ($studentsChunk) use (&$jobs, $availableCodes, $attendance, $teamId) {
                foreach ($studentsChunk as $student) {
                    $studentAttendance = $attendance->values;

                    $attendanceCode = '';
                    foreach ($studentAttendance as $value) {
                        if (in_array($student->uuid, Arr::get($value, 'uuids', []))) {
                            $attendanceCode = Arr::get($value, 'code');
                            break;
                        }
                    }

                    if (config('config.student.enable_attendance_notification_to_absentees') && $attendanceCode != 'A') {
                        continue;
                    }

                    $codeDetail = Arr::first($availableCodes, function ($code) use ($attendanceCode) {
                        return $code['code'] == $attendanceCode;
                    });

                    $variables = [
                        'name' => $student->name,
                        'course_name' => $student->course_name,
                        'batch_name' => $student->batch_name,
                        'course_batch_name' => $student->course_name.' - '.$student->batch_name,
                        'father_name' => $student->father_name,
                        'mother_name' => $student->mother_name,
                        'date' => $attendance->date->formatted,
                        'attendance' => Arr::get($codeDetail, 'label', '-'),
                        'app_name' => config('config.general.app_name'),
                        'team_name' => config('config.team.name'),
                    ];

                    $jobs[] = new SendAttendanceNotification([
                        'contact_id' => $student->contact_id,
                        'sender_user_id' => Arr::get($this->params, 'sender_user_id'),
                        'team_id' => $teamId,
                        'variables' => $variables,
                    ]);
                }
            });

        $meta = $attendance->getMeta('notification');
        $meta['notification']['sending_at'] = now()->toDateTimeString();
        $attendance->meta = $meta;
        $attendance->save();

        Bus::batch($jobs)
            ->then(function (Batch $batch) use ($attendance) {
                $meta = $attendance->getMeta('notification');
                $meta['notification']['sent_at'] = now()->toDateTimeString();
                $attendance->meta = $meta;
                $attendance->save();
            })
            ->catch(function (Batch $batch, Throwable $e) {})
            ->finally(function (Batch $batch) {})
            ->dispatch();
    }
}
