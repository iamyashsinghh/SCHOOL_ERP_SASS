<?php

namespace App\Services\Student\Report;

use App\Contracts\ListGenerator;
use App\Helpers\CalHelper;
use App\Http\Resources\Student\StudentSummaryResource;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\UserAccessLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class DailyAccessReportListService extends ListGenerator
{
    protected $allowedSorts = [];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'desc';

    public function getHeaders(Request $request): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('student.props.name'),
                'print_label' => 'name',
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        $startDate = $request->query('start_date', today()->subMonth()->toDateString());
        $endDate = $request->query('end_date', today()->toDateString());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $index = 0;
        while ($startDate->lte($endDate)) {
            $singleDay = $startDate->format('j_n');

            array_push($headers, [
                'key' => 'day_'.$singleDay,
                'label' => $startDate->format('d M'),
                'print_label' => 'access_counts.'.$index.'.total',
                'visibility' => true,
            ]);

            $index++;
            $startDate->addDay();
        }

        $headers[] = [
            'key' => 'total',
            'label' => trans('general.total'),
            'print_label' => 'access_total.total',
            'sortable' => false,
            'visibility' => true,
        ];

        return $headers;
    }

    public function filter(Request $request): AnonymousResourceCollection
    {
        $batch = Batch::query()
            ->whereUuid($request->batch)
            ->firstOrFail();

        $students = Student::query()
            ->summary()
            ->byPeriod()
            ->filterStudying()
            ->where('students.batch_id', $batch->id)
            ->get();

        $guardians = Guardian::query()
            ->join('contacts', 'guardians.contact_id', '=', 'contacts.id')
            ->whereIn('primary_contact_id', $students->pluck('contact_id'))
            ->select('guardians.*', 'contacts.user_id')
            ->get();

        $students = $students->map(function ($student) use ($guardians) {
            $student->guardian_ids = $guardians->where('primary_contact_id', $student->contact_id)->pluck('user_id')->toArray();

            return $student;
        });

        $studentUserIds = $students->pluck('user_id')->toArray();

        $guardianUserIds = $students->pluck('guardian_ids')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        $timezone = config('config.system.timezone');

        $startDate = $request->query('start_date', today()->subMonth()->toDateString());
        $endDate = $request->query('end_date', today()->toDateString());

        if (! CalHelper::validateDate($startDate) || ! CalHelper::validateDate($endDate)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        if ($startDate > $endDate) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay($timezone)->setTimezone('UTC');
        $endDateTime = Carbon::parse($endDate)->endOfDay($timezone)->setTimezone('UTC');

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        if ($startDate->diffInMonths($endDate) > 1) {
            throw ValidationException::withMessages(['message' => trans('student.report.daily_access_report.date_range_exceeded', ['attribute' => 30])]);
        }

        $accessLogs = UserAccessLog::query()
            ->select('id', 'created_at', 'user_id')
            ->where('event', 'dashboard.visit')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->whereIn('user_id', array_merge($studentUserIds, $guardianUserIds))
            ->get();

        $access = [];
        foreach ($students as $student) {
            $access[$student->uuid] = [
                'student' => 0,
                'guardian' => 0,
            ];
        }

        $studentAccessLogs = [];
        $guardianAccessLogs = [];
        foreach ($students as $student) {
            $toDate = clone $startDate;
            $accessCounts = [];
            $studentTotal = 0;
            $guardianTotal = 0;
            while ($toDate->lte($endDate)) {
                $singleDay = $toDate->format('j_n');

                $startPeriod = Carbon::parse($toDate->toDateString())->startOfDay($timezone)->setTimezone('UTC');
                $endPeriod = Carbon::parse($toDate->toDateString())->endOfDay($timezone)->setTimezone('UTC');

                $studentAccessCount = $accessLogs->where('user_id', $student->user_id)
                    ->whereBetween('created_at', [$startPeriod, $endPeriod])
                    ->count();

                $guardianAccessCount = $accessLogs->whereIn('user_id', $student->guardian_ids)
                    ->whereBetween('created_at', [$startPeriod, $endPeriod])
                    ->count();

                $accessCounts[] = [
                    'day' => $singleDay,
                    'date' => $toDate->toDateString(),
                    'student' => $studentAccessCount,
                    'guardian' => $guardianAccessCount,
                    'total' => $studentAccessCount + $guardianAccessCount,
                ];

                $studentTotal += $studentAccessCount;
                $guardianTotal += $guardianAccessCount;

                $studentAccessLogs[$singleDay] = ($studentAccessLogs[$singleDay] ?? 0) + $studentAccessCount;
                $guardianAccessLogs[$singleDay] = ($guardianAccessLogs[$singleDay] ?? 0) + $guardianAccessCount;

                $toDate->addDay();
            }

            $student->access_counts = $accessCounts;
            $student->access_total = [
                'student' => $studentTotal,
                'guardian' => $guardianTotal,
                'total' => $studentTotal + $guardianTotal,
            ];
        }

        $request->merge([
            'has_daily_access_report' => true,
        ]);

        $labels = [];
        while ($startDate->lte($endDate)) {
            $labels[] = $startDate->format('d M');
            $startDate->addDay();
        }

        $datasets = [
            [
                'label' => trans('student.student'),
                'data' => array_values($studentAccessLogs),
                'backgroundColor' => '#4E79A7',
                'stack' => 'total',
            ],
            [
                'label' => trans('guardian.guardian'),
                'data' => array_values($guardianAccessLogs),
                'backgroundColor' => '#F28E2B',
                'stack' => 'total',
            ],
        ];

        return StudentSummaryResource::collection($students)
            ->additional([
                'headers' => $this->getHeaders($request),
                'meta' => [
                    'layout' => [
                        'type' => 'full-page',
                    ],
                    'total' => $students->count(),
                    'filename' => 'Daily Access Report',
                    'chart_data' => [
                        'labels' => $labels,
                        'datasets' => $datasets,
                    ],
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->filter($request);
    }
}
