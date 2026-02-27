<?php

namespace App\Services\Student\Report;

use App\Helpers\CalHelper;
use App\Models\Academic\Batch;
use App\Models\Guardian;
use App\Models\Student\Student;
use App\Models\UserAccessLog;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DailyAccessReportService
{
    public function preRequisite(): array
    {
        $batches = Batch::getList();

        return compact('batches');
    }

    public function fetch($request): array
    {
        $date = $request->query('date', today()->toDateString());

        if (! CalHelper::validateDate($date)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $timezone = config('config.system.timezone');

        $startDateTime = Carbon::parse($date)->startOfDay($timezone)->setTimezone('UTC');
        $endDateTime = Carbon::parse($date)->endOfDay($timezone)->setTimezone('UTC');

        $activeUserIds = UserAccessLog::query()
            ->where('event', 'dashboard.visit')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->pluck('user_id')
            ->unique()
            ->values();

        $batches = Batch::query()
            ->select('batches.id', 'batches.uuid', 'batches.name as batch_name', 'courses.name as course_name')
            ->join('courses', 'batches.course_id', '=', 'courses.id')
            ->join('divisions', 'courses.division_id', '=', 'divisions.id')
            ->where('divisions.period_id', auth()->user()->current_period_id)
            ->orderBy('courses.position')
            ->orderBy('batches.position')
            ->get();

        $students = Student::query()
            ->select('students.id', 'students.batch_id', 'students.contact_id', 'contacts.user_id')
            ->join('batches', 'students.batch_id', '=', 'batches.id')
            ->join('contacts', 'students.contact_id', '=', 'contacts.id')
            ->join('admissions', 'students.admission_id', '=', 'admissions.id')
            ->where('students.period_id', auth()->user()->current_period_id)
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->get();

        $studentsByBatch = $students->groupBy('batch_id');

        $guardians = Guardian::query()
            ->select('guardians.primary_contact_id', 'contacts.user_id')
            ->join('contacts', 'guardians.contact_id', '=', 'contacts.id')
            ->whereIn('primary_contact_id', $students->pluck('contact_id'))
            ->get();

        $guardiansByStudent = $guardians->groupBy('primary_contact_id');

        $data = [];
        $grandTotalStudents = 0;
        $grandTotalActiveStudents = 0;

        foreach ($batches as $batch) {

            $batchStudents = $studentsByBatch->get($batch->id, collect());
            $totalStudents = $batchStudents->count();

            $activeDirect = $batchStudents
                ->whereIn('user_id', $activeUserIds)
                ->pluck('id')
                ->toArray();

            $inactiveStudents = $batchStudents
                ->whereNotIn('user_id', $activeUserIds);

            $activeViaGuardian = 0;

            foreach ($inactiveStudents as $student) {
                $guardianList = $guardiansByStudent->get($student->contact_id, collect());

                foreach ($guardianList as $guardian) {
                    if ($activeUserIds->contains($guardian->user_id)) {
                        $activeViaGuardian++;
                        break;
                    }
                }
            }

            $activeStudents = count($activeDirect) + $activeViaGuardian;

            $percent = $totalStudents > 0
                ? round(($activeStudents / $totalStudents) * 100, 2)
                : 0;

            $data[] = [
                'key' => $batch->uuid,
                'batch_name' => $batch->course_name.' - '.$batch->batch_name,
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'percent' => $percent,
                'percentage' => \Percent::from($percent)->formatted,
                'color' => \Percent::from($percent)->getPercentageColor(),
                'max' => $totalStudents,
            ];

            $grandTotalStudents += $totalStudents;
            $grandTotalActiveStudents += $activeStudents;
        }

        $grandTotalPercent = $grandTotalStudents > 0
            ? round(($grandTotalActiveStudents / $grandTotalStudents) * 100, 2)
            : 0;

        return [
            'overall_report' => true,
            'overall_data' => $data,
            'overall_summary' => [
                'total' => $grandTotalStudents,
                'active' => $grandTotalActiveStudents,
                'percent' => $grandTotalPercent,
                'percentage' => \Percent::from($grandTotalPercent)->formatted,
                'color' => \Percent::from($grandTotalPercent)->getPercentageColor(),
                'text_color' => \Percent::from($grandTotalPercent)->getPercentageTextColor(),
                'max' => $grandTotalStudents,
            ],
        ];
    }

    public function fetchForGraph($request): array
    {
        $date = $request->query('date', today()->toDateString());

        if (! CalHelper::validateDate($date)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $timezone = config('config.system.timezone');

        $startDateTime = Carbon::parse($date)->startOfDay($timezone)->setTimezone('UTC');
        $endDateTime = Carbon::parse($date)->endOfDay($timezone)->setTimezone('UTC');

        $accessLogs = UserAccessLog::query()
            ->select('id', 'user_id')
            ->where('event', 'dashboard.visit')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->get();

        $userIds = $accessLogs->pluck('user_id')->unique()->toArray();

        $batches = Batch::query()
            ->select('batches.id', 'batches.name as batch_name', 'courses.name as course_name')
            ->join('courses', 'batches.course_id', '=', 'courses.id')
            ->join('divisions', 'courses.division_id', '=', 'divisions.id')
            ->where('divisions.period_id', auth()->user()->current_period_id)
            ->orderBy('courses.position', 'asc')
            ->orderBy('batches.position', 'asc')
            ->get();

        $batchWiseStrength = Batch::query()
            ->leftJoin('students', 'batches.id', '=', 'students.batch_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereNull('students.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->whereIn('batches.id', $batches->pluck('id'))
            ->select(
                'batches.id as batch_id',
                'batches.name as batch_name',
                \DB::raw('COUNT(students.id) as student_count')
            )
            ->groupBy('batches.id', 'batches.name')
            ->orderBy('batches.name', 'asc')
            ->get();

        $students = Student::query()
            ->select('students.id', 'students.batch_id', 'students.contact_id')
            ->join('batches', 'students.batch_id', '=', 'batches.id')
            ->join('contacts', 'students.contact_id', '=', 'contacts.id')
            ->join('admissions', 'students.admission_id', '=', 'admissions.id')
            ->where('students.period_id', auth()->user()->current_period_id)
            ->whereIn('contacts.user_id', $userIds)
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->get();

        $guardians = Guardian::query()
            ->select('guardians.id', 'contacts.user_id')
            ->join('contacts', 'guardians.contact_id', '=', 'contacts.id')
            ->whereIn('primary_contact_id', $students->pluck('contact_id'))
            ->get();

        $labels = [];
        $studentData = [];
        $activeStudentData = [];
        foreach ($batches as $batch) {
            $labels[] = $batch->course_name.' - '.$batch->batch_name;
            $studentData[] = $batchWiseStrength->firstWhere('batch_id', $batch->id)->student_count ?? 0;
            $activeStudentData[] = $students->where('batch_id', $batch->id)->count();
        }

        $datasets = [
            [
                'label' => trans('student.students'),
                'data' => $studentData,
                'backgroundColor' => '#4E79A7',
            ],
            [
                'label' => trans('student.active_students'),
                'data' => $activeStudentData,
                'backgroundColor' => '#F28E2B',
            ],
        ];

        return [
            'overall_report' => true,
            'chart_data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
        ];
    }
}
