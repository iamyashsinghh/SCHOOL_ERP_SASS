<?php

namespace App\Services\Dashboard;

use App\Enums\Employee\Type;
use App\Enums\OptionType;
use App\Enums\ServiceType;
use App\Enums\Student\AttendanceSession;
use App\Models\Tenant\Academic\Course;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Attendance;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Student\Registration;
use App\Models\Tenant\Student\ServiceAllocation;
use App\Models\Tenant\Student\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StatService
{
    public function fetch(Request $request)
    {
        $stats = [
            $this->getStudentStat(),
            $this->getEmployeeStat(),
        ];

        $attendanceSummary = $this->getAttendanceSummary();

        $feeSummary = $this->getFeeSummary();

        $feeCollectionSummary = $this->getFeeCollectionSummary();

        $studentStrengthChartData = $this->getCourseWiseStrengthChartData($request);

        $transactionChartData = $this->getTransactionChartData($request);

        $serviceSummary = $this->getServiceStat();

        return compact('stats', 'attendanceSummary', 'feeSummary', 'feeCollectionSummary', 'studentStrengthChartData', 'transactionChartData', 'serviceSummary');
    }

    private function getStudentStat()
    {
        $student = Student::query()
            ->byPeriod()
            ->filterByStatus('studying')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->selectRaw("count('id') as total")
            ->first();

        $newStudents = Student::query()
            ->byPeriod()
            ->filterByStatus('studying')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereColumn('admissions.joining_date', '=', 'students.start_date')
            ->selectRaw("count('id') as total")
            ->first();

        return [
            'title' => trans('student.student'),
            'sub_title' => trans('general.new', ['attribute' => trans('student.student')]),
            'count' => $student->total,
            'sub_title_count' => $newStudents->total,
            'icon' => 'fas fa-user-graduate',
            'color' => 'bg-success',
            'sub_title_icon' => 'fas fa-arrow-up',
            'sub_title_color' => 'bg-success',
            'total' => $student->total,
        ];
    }

    private function getServiceStat()
    {
        $types = ServiceType::getOptions();

        $availableServices = explode(',', config('config.student.services'));

        $types = collect($types)->filter(function ($type) use ($availableServices) {
            return in_array(Arr::get($type, 'value'), $availableServices);
        })->values()->toArray();

        $student = Student::query()
            ->byPeriod()
            ->filterStudying()
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->selectRaw("count('id') as total")
            ->first();

        $serviceSummary = ServiceAllocation::query()
            ->selectRaw('service_allocations.type, COUNT(service_allocations.id) as total')
            ->join('students', 'students.id', '=', 'service_allocations.model_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereNull('students.cancelled_at')->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->where('students.period_id', auth()->user()->current_period_id)
            ->whereIn('service_allocations.type', Arr::pluck($types, 'value'))
            ->groupBy('service_allocations.type')
            ->get()
            ->map(function ($item) use ($student) {
                $type = ServiceType::getDetail($item->type);

                $percent = $student->total ? round(($item->total / $student->total) * 100, 2) : 0;

                return [
                    'code' => Arr::get($type, 'value'),
                    'label' => Arr::get($type, 'label'),
                    'value' => $item->total,
                    'percent' => $percent,
                    'percentage' => \Percent::from($percent)->formatted,
                    'color' => \Percent::from($percent)->getPercentageColor(),
                    'max' => $student->total,
                ];
            });

        return $serviceSummary;
    }

    private function getEmployeeStat()
    {
        $employee = Employee::query()
            ->byTeam()
            ->selectRaw("count('id') as total")
            ->whereIn('type', [Type::ADMINISTRATIVE, Type::TEACHING])
            ->first();

        $allEmployee = Employee::query()
            ->byTeam()
            ->selectRaw("count('id') as total")
            ->first();

        return [
            'title' => trans('employee.employee'),
            'count' => $employee->total,
            'sub_count' => $allEmployee->total,
            'icon' => 'fas fa-user-tie',
            'color' => 'bg-info',
            'total' => $employee->total,
        ];
    }

    private function getAttendanceSummary()
    {
        $students = Student::query()
            ->basic()
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->filterStudying()
            ->byPeriod()
            ->get();

        $batches = $students->pluck('batch_id')->all();

        $attendances = Attendance::query()
            ->whereIn('batch_id', $batches)
            ->where('date', '=', today()->toDateString())
            ->whereNull('subject_id')
            ->where('session', AttendanceSession::FIRST)
            ->whereIsDefault(1)
            ->get();

        $attendanceTypes = collect([
            ['code' => 'P', 'label' => trans('student.attendance.types.present'), 'count' => 0],
            ['code' => 'A', 'label' => trans('student.attendance.types.absent'), 'count' => 0],
        ]);

        $dbAttendanceTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get()
            ->map(function ($attendanceType) {
                return [
                    'code' => $attendanceType->getMeta('code'),
                    'label' => $attendanceType->name,
                    'count' => 0,
                ];
            });

        $attendanceTypes = $attendanceTypes->concat($dbAttendanceTypes);

        $total = $students->count();

        $allCounts = collect();
        foreach ($attendances as $attendance) {
            $counts = collect($attendance->values)->flatMap(function ($item) {
                return [$item['code'] => count($item['uuids'])];
            });

            $allCounts = $allCounts->mergeRecursive($counts);
        }

        $consolidatedCounts = $allCounts->map(function ($count) {
            return is_array($count) ? array_sum($count) : $count;
        });

        $attendanceSummary = $attendanceTypes->map(function ($type) use ($consolidatedCounts, $total) {
            $count = $consolidatedCounts->get($type['code'], 0);
            $percent = $total ? round(($count / $total) * 100, 2) : 0;

            return [
                'code' => $type['code'],
                'label' => $type['label'],
                'value' => $count,
                'percent' => $percent,
                'percentage' => \Percent::from($percent)->formatted,
                'color' => \Percent::from($percent)->getPercentageColor(),
                'max' => $total,
            ];
        })->values();

        return $attendanceSummary->toArray();
    }

    private function getFeeCollectionSummary()
    {
        $summary = Transaction::query()
            ->where('type', 'receipt')
            ->join('periods', 'periods.id', '=', 'transactions.period_id')
            ->where('periods.team_id', auth()->user()->current_team_id)
            ->selectRaw('SUM(CASE WHEN date = ? THEN amount ELSE 0 END) as today_collection', [today()->toDateString()])
            ->selectRaw('SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as week_collection', [today()->subDays(6)->toDateString(), today()->toDateString()])
            ->selectRaw('SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as month_collection', [today()->subDays(30)->toDateString(), today()->toDateString()])
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('transactions.is_online', 0)
                        ->orWhere(function ($q) {
                            $q->where('transactions.is_online', 1)->whereNotNull('processed_at');
                        });
                })->whereNull('transactions.cancelled_at')->whereNull('transactions.rejected_at');
            })
            ->first();

        return [
            [
                'label' => trans('dashboard.today_collection'),
                'value' => \Price::from($summary->today_collection),
            ],
            [
                'label' => trans('dashboard.week_collection'),
                'value' => \Price::from($summary->week_collection),
            ],
            [
                'label' => trans('dashboard.month_collection'),
                'value' => \Price::from($summary->month_collection),
            ],
        ];
    }

    private function getFeeSummary()
    {
        if (! auth()->user()->hasRole('admin')) {
            $summary = Student::query()
                ->leftJoin('student_fees', 'student_fees.student_id', '=', 'students.id')
                ->join('admissions', 'admissions.id', '=', 'students.admission_id')
                ->selectRaw('
                    COUNT(DISTINCT CASE WHEN student_fees.fee_concession_id IS NOT NULL THEN students.id END) AS with_concession,
                    COUNT(DISTINCT students.id) AS total
                ')
                ->byPeriod()
                ->filterStudying()
                ->first();

            $withConcession = $summary->with_concession;
            $total = $summary->total;
            $withoutConcession = $total - $withConcession;
            $withConcessionPercentage = $total > 0 ? round(($withConcession / $total) * 100, 2) : 0;
            $withoutConcessionPercentage = $total > 0 ? round(($withoutConcession / $total) * 100, 2) : 0;

            return [
                [
                    'label' => trans('student.fee.with_concession'),
                    'value' => $withConcession,
                    'percent' => $withConcessionPercentage,
                    'percentage' => \Percent::from($withConcessionPercentage)->formatted,
                    'color' => \Percent::from($withConcessionPercentage)->getPercentageColor(),
                    'max' => $total,
                ],
                [
                    'label' => trans('student.fee.without_concession'),
                    'value' => $withoutConcession,
                    'percent' => $withoutConcessionPercentage,
                    'percentage' => \Percent::from($withoutConcessionPercentage)->formatted,
                    'color' => \Percent::from($withoutConcessionPercentage)->getPercentageColor(),
                    'max' => $total,
                ],
            ];
        }

        $summary = Student::query()
            ->byPeriod()
            ->leftJoin('student_fees', 'students.id', '=', 'student_fees.student_id')
            ->selectRaw('SUM(student_fees.total) as total_fee')
            ->selectRaw('SUM(student_fees.paid) as paid_fee')
            ->selectRaw('SUM(student_fees.total - student_fees.paid) as balance_fee')
            ->selectRaw('SUM((SELECT SUM(concession) FROM student_fee_records WHERE student_fee_records.student_fee_id = student_fees.id)) as concession_fee')
            ->first();

        $paidPercentage = $summary->total_fee > 0 ? round(($summary->paid_fee / $summary->total_fee) * 100, 2) : 0;
        $balancePercentage = $summary->total_fee > 0 ? round(($summary->balance_fee / $summary->total_fee) * 100, 2) : 0;
        $concessionPercentage = $summary->concession_fee > 0 ? round(($summary->concession_fee / $summary->concession_fee) * 100, 2) : 0;

        return [
            [
                'label' => trans('finance.fee.paid'),
                'value' => \Price::from($summary->paid_fee)->formatted,
                'percent' => $paidPercentage,
                'percentage' => \Percent::from($paidPercentage)->formatted,
                'color' => \Percent::from($paidPercentage)->getPercentageColor(),
                'max' => \Price::from($summary->total_fee)->formatted,
            ],
            [
                'label' => trans('finance.fee.balance'),
                'value' => \Price::from($summary->balance_fee)->formatted,
                'percent' => $balancePercentage,
                'percentage' => \Percent::from($balancePercentage)->formatted,
                'color' => \Percent::from($balancePercentage)->getPercentageColor(),
                'max' => \Price::from($summary->total_fee)->formatted,
            ],
            [
                'label' => trans('finance.fee.concession'),
                'value' => \Price::from($summary->concession_fee)->formatted,
                'percent' => $concessionPercentage,
                'percentage' => \Percent::from($concessionPercentage)->formatted,
                'color' => \Percent::from($concessionPercentage)->getPercentageColor(),
                'max' => \Price::from($summary->concession_fee)->formatted,
            ],
        ];
    }

    private function getCourseWiseStrengthChartData(Request $request)
    {
        $type = $request->type ?? 'course_wise_strength';

        $courses = Course::query()
            ->byPeriod()
            ->leftJoin('batches', 'courses.id', '=', 'batches.course_id')
            ->leftJoin('students', 'batches.id', '=', 'students.batch_id')
            ->leftJoin('contacts', 'students.contact_id', '=', 'contacts.id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereNull('students.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->select(
                'courses.id as course_id',
                'courses.name as course_name',
                'courses.term as course_term',
                'contacts.gender',
                \DB::raw('COUNT(students.id) as student_count')
            )
            ->groupBy('courses.id', 'courses.name', 'courses.term', 'contacts.gender')
            ->orderBy('courses.position', 'asc')
            ->get();

        if ($type == 'gender_wise_strength') {
            $labels = [];
            $genderWiseData = [];

            foreach ($courses as $course) {
                $label = $course->course_name.' '.$course->course_term;

                if (! in_array($label, $labels)) {
                    $labels[] = $label;
                }

                $gender = $course->gender ?? 'N/A';
                $genderWiseData[$gender][$label] = $course->student_count;
            }

            $colors = ['#00CED1', '#FFA07A', '#9370DB', '#3CB371', '#FF69B4'];
            $courseDatasets = [];
            $genderIndex = 0;

            foreach ($genderWiseData as $gender => $dataByLabel) {
                $data = [];

                foreach ($labels as $label) {
                    $data[] = $dataByLabel[$label] ?? 0;
                }

                $courseDatasets[] = [
                    'label' => ucfirst($gender),
                    'data' => $data,
                    'backgroundColor' => $colors[$genderIndex % count($colors)],
                    'borderColor' => $colors[$genderIndex % count($colors)],
                ];

                $genderIndex++;
            }

            return [
                'labels' => $labels,
                'datasets' => $courseDatasets,
            ];
        }

        $courses = Course::query()
            ->byPeriod()
            ->leftJoin('batches', 'courses.id', '=', 'batches.course_id')
            ->leftJoin('students', 'batches.id', '=', 'students.batch_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereNull('students.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->select('courses.name as course_name', 'courses.term as course_term', \DB::raw('COUNT(students.id) as student_count'))
            ->groupBy('courses.id', 'courses.name', 'courses.term')
            ->orderBy('courses.position', 'asc')
            ->get();

        $admission = Course::query()
            ->byPeriod()
            ->leftJoin('batches', 'courses.id', '=', 'batches.course_id')
            ->leftJoin('students', 'batches.id', '=', 'students.batch_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereNull('students.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->whereColumn('admissions.joining_date', '=', 'students.start_date')
            ->select('courses.name as course_name', 'courses.term as course_term', \DB::raw('COUNT(students.id) as student_count'))
            ->groupBy('courses.id', 'courses.name', 'courses.term')
            ->orderBy('courses.position', 'asc')
            ->get();

        $registration = Registration::query()
            ->join('courses', 'courses.id', '=', 'registrations.course_id')
            ->select(
                'course_id',
                \DB::raw('COUNT(*) as total'),
                \DB::raw("COUNT(CASE WHEN status = 'initiated' THEN 1 END) as initiated"),
                \DB::raw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending"),
                \DB::raw("COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved"),
                \DB::raw("COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected")
            )
            ->byPeriod()
            ->whereNotNull('course_id')
            ->orderBy('courses.position', 'asc')
            ->groupBy('course_id')
            ->get();

        $labels = [];
        $data = [];
        foreach ($courses as $course) {
            $labels[] = $course->course_name.' '.$course->course_term;
            $data[] = $course->student_count;
        }

        $courseDatasets = [
            [
                'label' => trans('academic.student_strength'),
                'data' => $data,
                'backgroundColor' => '#00CED1',
                'borderColor' => '#00CED1',
            ],
        ];

        if ($type == 'course_wise_strength') {
            return [
                'labels' => $labels,
                'datasets' => $courseDatasets,
            ];
        } elseif ($type == 'admission_vs_strength') {
            array_push($courseDatasets, [
                'label' => trans('student.admission.admission'),
                'data' => $admission->pluck('student_count')->toArray(),
                'backgroundColor' => '#FACC15',
                'borderColor' => '#FACC15',
            ]);

            return [
                'labels' => $labels,
                'datasets' => $courseDatasets,
            ];
        }

        $registrationDatasets = [
            [
                'label' => trans('student.registration.total_registration'),
                'data' => $registration->pluck('total')->toArray(),
                'backgroundColor' => '#4F46E5',
                'borderColor' => '#4F46E5',
            ],
            [
                'label' => trans('student.registration.initiated_registration'),
                'data' => $registration->pluck('initiated')->toArray(),
                'backgroundColor' => '#0EA5E9',
                'borderColor' => '#0EA5E9',
            ],
            [
                'label' => trans('student.registration.pending_registration'),
                'data' => $registration->pluck('pending')->toArray(),
                'backgroundColor' => '#FACC15',
                'borderColor' => '#FACC15',
            ],
            [
                'label' => trans('student.registration.approved_registration'),
                'data' => $registration->pluck('approved')->toArray(),
                'backgroundColor' => '#10B981',
                'borderColor' => '#10B981',
            ],
            [
                'label' => trans('student.registration.rejected_registration'),
                'data' => $registration->pluck('rejected')->toArray(),
                'backgroundColor' => '#EF4444',
                'borderColor' => '#EF4444',
            ],
        ];

        if ($type == 'registration_summary') {
            return [
                'labels' => $labels,
                'datasets' => $registrationDatasets,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => trans('academic.student_strength'),
                    'data' => $data,
                    'backgroundColor' => '#00CED1',
                    'borderColor' => '#00CED1',
                ],
                ...$registrationDatasets,
            ],
        ];
    }

    private function getTransactionChartData(Request $request)
    {
        $type = $request->type ?? 'transaction_summary';

        if ($type == 'course_wise_summary') {
            $courses = Course::query()
                ->byPeriod()
                ->select('id', 'name', 'term', 'position')
                ->orderBy('position', 'asc')
                ->get();

            $courseWiseSummary = Student::query()
                ->byPeriod()
                ->selectRaw('courses.id as course_id, courses.name as course_name, courses.term as course_term')
                ->selectRaw('SUM(student_fees.total) as total_fee')
                ->selectRaw('SUM(student_fees.paid) as paid_fee')
                ->selectRaw('SUM(student_fees.total - student_fees.paid) as balance_fee')
                ->selectRaw('SUM((
                    SELECT SUM(concession)
                    FROM student_fee_records
                    WHERE student_fee_records.student_fee_id = student_fees.id
                )) as concession_fee')
                ->join('batches', 'students.batch_id', '=', 'batches.id')
                ->join('courses', 'batches.course_id', '=', 'courses.id')
                ->leftJoin('student_fees', 'students.id', '=', 'student_fees.student_id')
                ->whereNotNull('students.fee_structure_id')
                ->groupBy('courses.id', 'courses.name', 'courses.term')
                ->orderBy('courses.position')
                ->get();

            $datasets = [
                [
                    'label' => trans('finance.fee.total'),
                    'data' => $courseWiseSummary->pluck('total_fee')->toArray(),
                    'backgroundColor' => '#4F46E5',
                    'borderColor' => '#4F46E5',
                ],
                [
                    'label' => trans('finance.fee.paid'),
                    'data' => $courseWiseSummary->pluck('paid_fee')->toArray(),
                    'backgroundColor' => '#10B981',
                    'borderColor' => '#10B981',
                ],
                [
                    'label' => trans('finance.fee.balance'),
                    'data' => $courseWiseSummary->pluck('balance_fee')->toArray(),
                    'backgroundColor' => '#EF4444',
                    'borderColor' => '#EF4444',
                ],
                [
                    'label' => trans('finance.fee.concession'),
                    'data' => $courseWiseSummary->pluck('concession_fee')->toArray(),
                    'backgroundColor' => '#FACC15',
                    'borderColor' => '#FACC15',
                ],
            ];

            $labels = [];
            foreach ($courses as $course) {
                $labels[] = $course->name.' '.$course->term;
            }

            return [
                'labels' => $labels,
                'datasets' => $datasets,
            ];
        } elseif ($type == 'concession_summary') {
            $latestFees = Fee::query()
                ->select(
                    'student_fees.id',
                    'student_fees.student_id',
                    'fee_concessions.name as concession_name',
                    'contacts.gender'
                )
                ->join('fee_concessions', 'student_fees.fee_concession_id', '=', 'fee_concessions.id')
                ->leftJoin('fee_installments', 'student_fees.fee_installment_id', '=', 'fee_installments.id')
                ->join('students', 'students.id', '=', 'student_fees.student_id')
                ->join('contacts', 'contacts.id', '=', 'students.contact_id')
                ->where('students.period_id', auth()->user()->current_period_id)
                ->whereIn('student_fees.id', function ($query) {
                    $query->select(\DB::raw('MAX(student_fees2.id)'))
                        ->from('student_fees as student_fees2')
                        ->leftJoin('fee_installments as fee_installments2', 'student_fees2.fee_installment_id', '=', 'fee_installments2.id')
                        ->groupBy('student_fees2.student_id');
                })
                ->orderBy(\DB::raw('COALESCE(student_fees.due_date, fee_installments.due_date)'))
                ->get();

            $grouped = $latestFees->groupBy('concession_name');

            $genders = $latestFees->pluck('gender')->unique()->values()->toArray();

            $labels = $grouped->keys()->toArray();

            $datasets = [];
            $colors = [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
            ];

            $datasets[] = [
                'label' => trans('general.total'),
                'data' => $grouped->map->count()->values()->toArray(),
                'backgroundColor' => array_shift($colors),
            ];

            foreach ($genders as $index => $gender) {
                $datasets[] = [
                    'label' => ucfirst($gender ?: 'Unknown'),
                    'data' => $grouped->map(function ($items) use ($gender) {
                        return $items->where('gender', $gender)->count();
                    })->values()->toArray(),
                    'backgroundColor' => array_shift($colors),
                ];
            }

            return [
                'labels' => $labels,
                'datasets' => $datasets,
            ];
        }

        $monthRanges = [];
        $baseDate = now()->startOfMonth();

        for ($i = 11; $i >= 0; $i--) {
            $date = $baseDate->copy()->subMonths($i);
            $monthRanges[] = [
                'start' => $date->toDateString(),
                'end' => $date->copy()->endOfMonth()->toDateString(),
            ];
        }

        $transactions = Transaction::selectRaw('MONTH(date) as month, SUM(CASE WHEN type = "receipt" THEN amount ELSE 0 END) as receipt, SUM(CASE WHEN type = "payment" THEN amount ELSE 0 END) as payment')
            ->whereHas('period', function ($q) {
                $q->where('team_id', auth()->user()->current_team_id);
            })
            ->whereIn('type', ['receipt', 'payment'])
            ->whereBetween('date', [$monthRanges[0]['start'], $monthRanges[11]['end']])
            ->whereNull('transactions.cancelled_at')
            ->where(function ($q) {
                $q->whereIsOnline(false)
                    ->orWhere(function ($q) {
                        $q->whereIsOnline(true)
                            ->whereNotNull('transactions.processed_at');
                    });
            })
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthData = [];
        foreach ($transactions as $transaction) {
            $monthData[$transaction->month] = [
                'receipt' => $transaction->receipt ?? 0,
                'payment' => $transaction->payment ?? 0,
            ];
        }

        $receiptData = [];
        $paymentData = [];
        foreach ($monthRanges as $monthRange) {
            $monthLabel = Carbon::parse($monthRange['start'])->format('F Y');
            $month = Carbon::parse($monthRange['start'])->format('n');
            $labels[] = $monthLabel;

            if (isset($monthData[$month])) {
                $receiptData[] = $monthData[$month]['receipt'];
                $paymentData[] = $monthData[$month]['payment'];
            } else {
                $receiptData[] = 0;
                $paymentData[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => trans('finance.transaction.types.receipt'),
                    'data' => $receiptData,
                    'backgroundColor' => '#FF8C00',
                    'borderColor' => '#FF8C00',
                ],
                [
                    'label' => trans('finance.transaction.types.payment'),
                    'data' => $paymentData,
                    'backgroundColor' => '#483D8B',
                    'borderColor' => '#483D8B',
                ],
            ],
        ];
    }

    public function fetchStudentChartData(Request $request)
    {
        return $this->getCourseWiseStrengthChartData($request);
    }

    public function fetchTransactionChartData(Request $request)
    {
        return $this->getTransactionChartData($request);
    }
}
