<?php

namespace App\Services\Student;

use App\Enums\OptionType;
use App\Models\Tenant\Academic\Period;
use App\Models\Tenant\Calendar\Holiday;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Attendance;
use App\Models\Tenant\Student\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StudentWiseAttendanceService
{
    public function fetch(Request $request, Student $student)
    {
        $cacheKey = "student_attendances_{$student->uuid}";

        $attendanceTypes = collect([
            ['code' => 'P', 'label' => trans('student.attendance.types.present'), 'color' => '#28a745', 'key' => 'present'],
            ['code' => 'A', 'label' => trans('student.attendance.types.absent'), 'color' => '#dc3545', 'key' => 'absent'],
            ['code' => 'H', 'label' => trans('student.attendance.types.holiday'), 'color' => '#330F57', 'key' => 'holiday'],
        ]);

        $dbAttendanceTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get()
            ->map(function ($attendanceType) {
                return [
                    'code' => $attendanceType->getMeta('code'),
                    'label' => $attendanceType->name,
                    'color' => $attendanceType->getMeta('color') ?? 'bg-primary',
                    'key' => Str::camel($attendanceType->name),
                ];
            });

        $attendanceTypes = $attendanceTypes->concat($dbAttendanceTypes);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($request, $student, $attendanceTypes) {
            $period = Period::query()
                ->findOrFail($student->period_id);

            $holidays = Holiday::query()
                ->where('start_date', '>=', $period->start_date->value)
                ->where('end_date', '<=', $period->end_date->value)
                ->get();

            $attendances = Attendance::query()
                ->whereBatchId($student->batch_id)
                ->orderBy('date', 'asc')
                ->get();

            $startDate = Carbon::parse($period->start_date->value)->startOfMonth();
            $endDate = Carbon::parse($period->end_date->value)->endOfMonth();

            $months = [];
            $summary = [];
            while ($startDate->lte($endDate)) {
                $months[] = $startDate->format('M Y');

                foreach ($attendanceTypes as $type) {
                    $key = Arr::get($type, 'code');
                    if (! isset($summary[$startDate->format('M Y')][$key])) {
                        $summary[$startDate->format('M Y')][$key] = 0;
                    }
                }

                $startDate->addMonth();
            }

            $rows = [];
            $header = [];

            array_push($header, [
                'key' => 'date',
                'label' => trans('general.date'),
            ]);

            for ($i = 1; $i <= 31; $i++) {
                $row = [];

                array_push($row, [
                    'key' => 'day_'.$i,
                    'name' => trans('general.date'),
                    'label' => $i,
                ]);

                foreach ($months as $month) {
                    if ($i == 1) {
                        $header[] = [
                            'key' => $month,
                            'label' => $month,
                        ];
                    }

                    $date = Carbon::parse($month)->startOfMonth()->addDays($i - 1);

                    if ($date->format('M Y') != $month) {
                        $row[] = [
                            'key' => $date->toDateString(),
                            'name' => trans('general.date'),
                            'label' => '',
                        ];
                    } else {
                        $attendance = $this->getAttendanceCode($student, $holidays, $attendances, $date->toDateString());

                        if ($attendance == 'P') {
                            $summary[$date->format('M Y')]['P']++;
                        } elseif ($attendance == 'A') {
                            $summary[$date->format('M Y')]['A']++;
                        } elseif ($attendance == 'H') {
                            $summary[$date->format('M Y')]['H']++;
                        } else {
                            if (in_array($attendance, $attendanceTypes->pluck('code')->toArray())) {
                                $summary[$date->format('M Y')][$attendance]++;
                            }
                        }

                        $icon = '';
                        $color = '';
                        if ($attendance == 'H') {
                            $icon = 'fas fa-circle-h';
                            $color = 'text-primary dark:text-gray-400';
                        } elseif ($attendance == 'P') {
                            $icon = 'far fa-check-circle';
                            $color = 'text-success';
                        } elseif ($attendance == 'A') {
                            $icon = 'far fa-times-circle';
                            $color = 'text-danger';
                        } else {
                            $icon = '';
                            $color = 'text-gray-600';
                        }

                        $row[] = [
                            'key' => $date->toDateString(),
                            'name' => $month,
                            'label' => $attendance,
                            'icon' => $icon,
                            'color' => $color,
                        ];
                    }
                }

                $rows[] = [
                    'key' => 'day_'.$i,
                    'row' => $row,
                ];
            }

            $summaryRow = [];

            foreach ($attendanceTypes as $type) {
                $key = Arr::get($type, 'code');
                $summaryRow[$key][] = [
                    'key' => $key,
                    'label' => Arr::get($type, 'code'),
                ];
            }

            foreach ($months as $month) {
                foreach ($attendanceTypes as $type) {
                    $key = Arr::get($type, 'code');
                    $summaryRow[$key][] = [
                        'key' => $month,
                        'label' => $summary[$month][$key],
                        'count' => (int) $summary[$month][$key],
                    ];
                }
            }

            $total = [
                'working_days' => [
                    'key' => 'working_days',
                    'label' => 'WD',
                    'description' => trans('student.attendance.types.working_days'),
                    'count' => $attendances->count(),
                    'color' => '#27C5F5',
                ],
            ];

            foreach ($attendanceTypes as $attendanceType) {
                $key = Arr::get($attendanceType, 'key');
                $code = Arr::get($attendanceType, 'code');
                $total[$key] = [
                    'key' => $key,
                    'label' => Arr::get($attendanceType, 'code'),
                    'description' => Arr::get($attendanceType, 'label'),
                    'count' => collect($summaryRow[$code])->sum('count'),
                    'color' => Arr::get($attendanceType, 'color'),
                ];
            }

            foreach ($attendanceTypes as $attendanceType) {
                $rows[] = [
                    'type' => 'footer',
                    'key' => Str::camel(Arr::get($attendanceType, 'key')),
                    'row' => $summaryRow[Arr::get($attendanceType, 'code')],
                ];
            }

            $chartData = [];
            $labels = $months;

            $chartData['labels'] = $labels;

            $datasets = [];
            foreach ($attendanceTypes as $attendanceType) {
                $key = Arr::get($attendanceType, 'key');
                $code = Arr::get($attendanceType, 'code');
                $datasets[] = [
                    'label' => Arr::get($attendanceType, 'label'),
                    'data' => collect($summaryRow[$code])->skip(1)->pluck('count'),
                    'backgroundColor' => Arr::get($attendanceType, 'color'),
                    'borderColor' => Arr::get($attendanceType, 'color'),
                ];
            }

            $chartData['datasets'] = $datasets;

            return compact('rows', 'header', 'total', 'chartData');
        });
    }

    private function getAttendanceCode(Student $student, Collection $holidays, Collection $attendances, string $date)
    {
        $holiday = $holidays->filter(function ($holiday) use ($date) {
            return $holiday->start_date->value <= $date && $holiday->end_date->value >= $date;
        })->first();

        $attendance = $attendances->firstWhere('date.value', $date);

        if ($student->leaving_date && $student->leaving_date < $date) {
            // left
            return '';
        } elseif ($student->start_date->value > $date) {
            // not_started
            return '';
        } elseif (! $attendance && $holiday) {
            return 'H';
        } elseif ($attendance) {
            if (Arr::get($attendance, 'meta.is_holiday')) {
                return 'H';
            } else {
                $values = Arr::get($attendance, 'values', []);

                $attendanceCode = null;
                foreach ($values as $value) {
                    if (in_array($student->uuid, Arr::get($value, 'uuids', []))) {
                        $attendanceCode = Arr::get($value, 'code');
                    }
                }

                return $attendanceCode;
            }
        }

        return '';
    }
}
