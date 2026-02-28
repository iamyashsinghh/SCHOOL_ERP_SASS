<?php

namespace App\Services\Finance\Report;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\FeeHeadResource;
use App\Http\Resources\Finance\Report\FeeSummaryListResource;
use App\Models\Tenant\Finance\FeeGroup;
use App\Models\Tenant\Finance\FeeHead;
use App\Models\Tenant\Finance\FeeInstallment;
use App\Models\Tenant\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FeeSummaryListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'code_number', 'name', 'total_fee', 'paid_fee', 'balance_fee', 'concession_fee'];

    protected $defaultSort = 'code_number';

    protected $defaultOrder = 'asc';

    public function getHeaders(Request $request): array
    {
        $headers = [
            [
                'key' => 'sno',
                'label' => trans('general.sno'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'codeNumber',
                'label' => trans('student.admission.props.code_number'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'name',
                'label' => trans('student.props.name'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'fatherName',
                'label' => trans('contact.props.father_name'),
                'print_label' => 'father_name',
                'print_sub_label' => 'contact_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'course',
                'label' => trans('academic.course.course'),
                'print_label' => 'course_name + batch_name',
                // 'print_sub_label' => 'batch_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('contact.category.category'),
                'print_label' => 'category.name',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'feeStructure',
                'label' => trans('finance.fee_structure.fee_structure'),
                'print_label' => 'fee_structure.name',
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        if ($request->boolean('head_wise_detail')) {
            $feeHeads = $request->get('fee_heads', []);

            foreach ($feeHeads as $feeHead) {
                $headers[] = [
                    'key' => Str::camel($feeHead->slug),
                    'label' => $feeHead->name,
                    'type' => 'currency',
                    'print_label' => Str::camel($feeHead->slug).'.formatted',
                    'sortable' => false,
                    'visibility' => true,
                ];
            }

            $headers[] = [
                'key' => 'transportFee',
                'label' => trans('finance.fee.default_fee_heads.transport_fee'),
                'type' => 'currency',
                'print_label' => 'transportFee.formatted',
                'sortable' => false,
                'visibility' => true,
            ];

            $headers[] = [
                'key' => 'lateFee',
                'label' => trans('finance.fee.default_fee_heads.late_fee'),
                'type' => 'currency',
                'print_label' => 'lateFee.formatted',
                'sortable' => false,
                'visibility' => true,
            ];
        }

        array_push($headers, [
            'key' => 'totalFee',
            'label' => trans('finance.fee.total'),
            'type' => 'currency',
            'print_label' => 'total.formatted',
            'sortable' => true,
            'visibility' => true,
        ]);

        array_push($headers, [
            'key' => 'concessionFee',
            'label' => trans('finance.fee.concession'),
            'type' => 'currency',
            'print_label' => 'concession.formatted',
            'sortable' => true,
            'visibility' => true,
        ]);

        array_push($headers, [
            'key' => 'paidFee',
            'label' => trans('finance.fee.paid'),
            'type' => 'currency',
            'print_label' => 'paid.formatted',
            'sortable' => true,
            'visibility' => true,
        ]);

        array_push($headers, [
            'key' => 'balanceFee',
            'label' => trans('finance.fee.balance'),
            'type' => 'currency',
            'print_label' => 'balance.formatted',
            'sortable' => true,
            'visibility' => true,
        ]);

        // if (request()->ajax()) {
        //     $headers[] = $this->actionHeader;
        // }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $minTotal = $request->query('min_total', 0);
        $minPaid = $request->query('min_paid', 0);
        $minConcession = $request->query('min_concession', 0);
        $minBalance = $request->query('min_balance', 0);

        $groups = Str::toArray($request->query('groups'));
        $feeStructures = Str::toArray($request->query('fee_structures'));

        $status = $request->query('status');

        $installmentTitle = $request->query('fee_installment');

        return Student::query()
            ->with('feeStructure')
            ->summary()
            ->byPeriod()
            ->filterAccessible()
            ->selectRaw('categories.name as category_name')
            ->selectRaw('SUM(student_fees.total) as total_fee')
            ->selectRaw('SUM(student_fees.paid) as paid_fee')
            ->selectRaw('SUM(student_fees.total - student_fees.paid) as balance_fee')
            ->selectRaw('(SELECT SUM(student_fee_records.concession) FROM student_fee_records WHERE student_fee_records.student_fee_id IN (SELECT id FROM student_fees WHERE student_fees.student_id = students.id'.(isset($installmentTitle) ? ' AND student_fees.fee_installment_id IN (SELECT id FROM fee_installments WHERE title = "'.addslashes($installmentTitle).'")' : '').')) as concession_fee')
            ->leftJoin('options as categories', 'contacts.category_id', '=', 'categories.id')
            ->leftJoin('student_fees', 'students.id', '=', 'student_fees.student_id')
            ->leftJoin('fee_installments', 'student_fees.fee_installment_id', '=', 'fee_installments.id')
            ->whereNotNull('students.fee_structure_id')
            ->when($request->fee_installments, function ($q, $feeInstallments) {
                $q->whereIn('student_fees.fee_installment_id', $feeInstallments);
            })
            ->when($installmentTitle, function ($q, $installmentTitle) {
                $q->where('fee_installments.title', 'like', "%{$installmentTitle}%");
            })
            ->filterByStatus($status)
            ->when($request->query('name'), function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($minTotal, function ($q) use ($minTotal) {
                $q->havingRaw('SUM(student_fees.total) >= ?', [$minTotal]);
            })
            ->when($minPaid, function ($q) use ($minPaid) {
                $q->havingRaw('SUM(student_fees.paid) >= ?', [$minPaid]);
            })
            ->when($minConcession, function ($q) use ($minConcession) {
                $q->havingRaw('(SELECT SUM(student_fee_records.concession) FROM student_fee_records WHERE student_fee_records.student_fee_id IN (SELECT id FROM student_fees WHERE student_fees.student_id = students.id)) >= ?', [$minConcession]);
            })
            ->when($minBalance, function ($q) use ($minBalance) {
                $q->havingRaw('SUM(student_fees.total - student_fees.paid) >= ?', [$minBalance]);
            })
            ->when($request->query('category'), function ($q, $category) {
                $q->where('categories.uuid', $category);
            })
            ->when($feeStructures, function ($q, $feeStructures) {
                $q->whereHas('feeStructure', function ($q) use ($feeStructures) {
                    $q->whereIn('uuid', $feeStructures);
                });
            })
            ->when($groups, function ($q, $groups) {
                $q->leftJoin('group_members', 'students.id', 'group_members.model_id')
                    ->where('group_members.model_type', 'Student')
                    ->join('options as student_groups', 'group_members.model_group_id', 'student_groups.id')
                    ->whereIn('student_groups.uuid', $groups);
            })
            ->when($request->query('address'), function ($q, $address) {
                $q->filterByAddress($address);
            })
            ->filter([
                'App\QueryFilters\LikeMatch:code_number',
                'App\QueryFilters\WhereInMatch:batches.uuid,batches',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $minTotal = $request->query('min_total', 0);
        $minPaid = $request->query('min_paid', 0);
        $minConcession = $request->query('min_concession', 0);
        $minBalance = $request->query('min_balance', 0);

        $groups = Str::toArray($request->query('groups'));
        $feeStructures = Str::toArray($request->query('fee_structures'));

        $feeGroup = $request->query('fee_group');
        $feeInstallments = [];

        if ($feeGroup) {
            $feeGroup = FeeGroup::query()
                ->where('uuid', $feeGroup)
                ->first();

            $feeInstallments = FeeInstallment::query()
                ->where('fee_group_id', $feeGroup?->id)
                ->get()
                ->pluck('id')
                ->all();
        }

        $request->merge([
            'fee_installments' => $feeInstallments,
        ]);

        $installmentTitle = $request->query('fee_installment');

        $status = $request->query('status');

        $summary = Student::query()
            ->summaryWithoutSelect()
            ->byPeriod()
            ->filterAccessible()
            ->leftJoin('student_fees', 'students.id', '=', 'student_fees.student_id')
            ->leftJoin('fee_installments', 'student_fees.fee_installment_id', '=', 'fee_installments.id')
            ->leftJoin('options as categories', 'contacts.category_id', '=', 'categories.id')
            ->selectRaw('SUM(student_fees.total) as total_fee')
            ->selectRaw('SUM(student_fees.paid) as paid_fee')
            ->selectRaw('SUM(student_fees.total - student_fees.paid) as balance_fee')
            ->selectRaw('SUM((SELECT SUM(concession) FROM student_fee_records WHERE student_fee_records.student_fee_id = student_fees.id'.(isset($installmentTitle) ? ' AND student_fees.fee_installment_id IN (SELECT id FROM fee_installments WHERE title = "'.addslashes($installmentTitle).'")' : '').')) as concession_fee')
            ->whereNotNull('students.fee_structure_id')
            ->when($request->fee_installments, function ($q, $feeInstallments) {
                $q->whereIn('student_fees.fee_installment_id', $feeInstallments);
            })
            ->when($installmentTitle, function ($q, $installmentTitle) {
                $q->where('fee_installments.title', 'like', "%{$installmentTitle}%");
            })
            ->filterByStatus($status)
            ->when($request->query('name'), function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($feeStructures, function ($q, $feeStructures) {
                $q->whereHas('feeStructure', function ($q) use ($feeStructures) {
                    $q->whereIn('uuid', $feeStructures);
                });
            })
            ->when($groups, function ($q, $groups) {
                $q->leftJoin('group_members', 'students.id', 'group_members.model_id')
                    ->where('group_members.model_type', 'Student')
                    ->join('options as student_groups', 'group_members.model_group_id', 'student_groups.id')
                    ->whereIn('student_groups.uuid', $groups);
            })
            ->when($request->query('address'), function ($q, $address) {
                $q->filterByAddress($address);
            })
            // ->when($minTotal, function ($q) use ($minTotal) {
            //     $q->havingRaw('SUM(student_fees.total) >= ?', [$minTotal]);
            // })
            // ->when($minPaid, function ($q) use ($minPaid) {
            //     $q->havingRaw('SUM(student_fees.paid) >= ?', [$minPaid]);
            // })
            // ->when($minConcession, function ($q) use ($minConcession) {
            //     $q->havingRaw('SUM((SELECT SUM(concession) FROM student_fee_records WHERE student_fee_records.student_fee_id = student_fees.id)) >= ?', [$minConcession]);
            // })
            // ->when($minBalance, function ($q) use ($minBalance) {
            //     $q->havingRaw('SUM(student_fees.total - student_fees.paid) >= ?', [$minBalance]);
            // })
            ->when($request->query('category'), function ($q, $category) {
                $q->where('categories.uuid', $category);
            })
            ->filter([
                'App\QueryFilters\LikeMatch:code_number',
                'App\QueryFilters\WhereInMatch:batches.uuid,batches',
            ])
            ->first();

        $records = $this->filter($request)
            ->groupBy('students.id')
            ->orderBy($this->getSort(), $this->getOrder())
            ->when($request->query('output') == 'export_all_excel', function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            });

        if ($request->boolean('head_wise_detail')) {
            $feeHeads = FeeHead::query()
                ->byPeriod($request->period_id)
                ->get();

            $request->merge([
                'fee_heads' => $feeHeads,
            ]);

            foreach ($feeHeads as $feeHead) {
                $name = Str::camel($feeHead->slug);
                if ($request->query('head_wise_detail_type') == 'balance') {
                    $selectExpressions[] = \DB::raw("SUM(CASE WHEN fee_head_id = $feeHead->id THEN (student_fee_records.amount - student_fee_records.paid) ELSE 0 END) as $name");
                } elseif ($request->query('head_wise_detail_type') == 'paid') {
                    $selectExpressions[] = \DB::raw("SUM(CASE WHEN fee_head_id = $feeHead->id THEN (student_fee_records.paid) ELSE 0 END) as $name");
                } else {
                    $selectExpressions[] = \DB::raw("SUM(CASE WHEN fee_head_id = $feeHead->id THEN (student_fee_records.amount) ELSE 0 END) as $name");
                }
            }

            if ($request->query('head_wise_detail_type') == 'balance') {
                $selectExpressions[] = \DB::raw("SUM(CASE WHEN default_fee_head = 'transport_fee' THEN (student_fee_records.amount - student_fee_records.paid) ELSE 0 END) as transportFee");
                $selectExpressions[] = \DB::raw("SUM(CASE WHEN default_fee_head = 'late_fee' THEN (student_fee_records.amount - student_fee_records.paid) ELSE 0 END) as lateFee");
            } elseif ($request->query('head_wise_detail_type') == 'paid') {
                $selectExpressions[] = \DB::raw("SUM(CASE WHEN default_fee_head = 'transport_fee' THEN (student_fee_records.paid) ELSE 0 END) as transportFee");
                $selectExpressions[] = \DB::raw("SUM(CASE WHEN default_fee_head = 'late_fee' THEN (student_fee_records.paid) ELSE 0 END) as lateFee");
            } else {
                $selectExpressions[] = \DB::raw("SUM(CASE WHEN default_fee_head = 'transport_fee' THEN (student_fee_records.amount) ELSE 0 END) as transportFee");
                $selectExpressions[] = \DB::raw("SUM(CASE WHEN default_fee_head = 'late_fee' THEN (student_fee_records.amount) ELSE 0 END) as lateFee");
            }

            $headWiseRecords = Student::query()
                ->select('students.id as student_id', ...$selectExpressions)
                ->leftJoin('student_fees', 'students.id', '=', 'student_fees.student_id')
                ->leftJoin('student_fee_records', 'student_fees.id', '=', 'student_fee_records.student_fee_id')
                ->whereIn('students.id', $records->pluck('id'))
                ->groupBy('students.id')
                ->get();

            $request->merge([
                'head_wise_records' => $headWiseRecords,
            ]);
        }

        return FeeSummaryListResource::collection($records)
            ->additional([
                'headers' => $this->getHeaders($request),
                'meta' => [
                    'layout' => [
                        'type' => 'full-page',
                    ],
                    'filename' => 'Fee Summary Report',
                    'sno' => $this->getSno(),
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                    'has_footer' => true,
                    'fee_heads' => FeeHeadResource::collection($request->fee_heads ?? collect([])),
                ],
                'footers' => [
                    ['key' => 'codeNumber', 'label' => trans('general.total')],
                    ['key' => 'name', 'label' => ''],
                    ['key' => 'fatherName', 'label' => ''],
                    ['key' => 'course', 'label' => ''],
                    ['key' => 'totalFee', 'label' => \Price::from($summary->total_fee)->formatted],
                    ['key' => 'concessionFee', 'label' => \Price::from($summary->concession_fee)->formatted],
                    ['key' => 'paidFee', 'label' => \Price::from($summary->paid_fee)->formatted],
                    ['key' => 'balanceFee', 'label' => \Price::from($summary->balance_fee)->formatted],
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
