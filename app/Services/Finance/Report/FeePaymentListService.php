<?php

namespace App\Services\Finance\Report;

use App\Contracts\ListGenerator;
use App\Enums\Finance\TransactionStatus;
use App\Http\Resources\Finance\Report\FeePaymentListResource;
use App\Models\Academic\Period;
use App\Models\Finance\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FeePaymentListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'serial_number', 'voucher_number', 'name', 'date'];

    protected $defaultSort = 'date';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'voucherNumber',
                'label' => trans('finance.transaction.voucher'),
                'print_label' => 'voucher_number',
                'print_sub_label' => 'reference_number',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'status',
                'label' => trans('finance.transaction.props.status'),
                'print_label' => 'status.label',
                'print_sub_label' => 'status.value',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'name',
                'label' => trans('student.props.name'),
                'print_label' => 'name',
                'print_sub_label' => 'code_number',
                'print_additional_label' => 'fee_type',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'codeNumber',
                'label' => trans('student.admission.props.code_number'),
                'print_label' => 'code_number',
                'sortable' => false,
                'visibility' => false,
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
                'print_label' => 'category_name',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'amount',
                'label' => trans('finance.transaction.props.amount'),
                'type' => 'currency',
                'print_label' => 'amount.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'date',
                'label' => trans('finance.transaction.props.date'),
                'type' => 'date',
                'print_label' => 'date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'ledger',
                'label' => trans('finance.ledger.ledger'),
                'print_label' => 'payment.ledger.name',
                'print_sub_label' => 'payment.method_name',
                'print_additional_label' => 'payment.summary',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'paymentMethod',
                'label' => trans('finance.payment_method.payment_method'),
                'print_label' => 'payment.method_name',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'feeInstallments',
                'label' => trans('finance.fee_structure.installment'),
                'print_label' => 'fee_installments',
                'print_sub_label' => 'fee_group',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'feeHeads',
                'label' => trans('finance.fee_head.fee_head'),
                'type' => 'array',
                'print_label' => 'fee_payments',
                'print_key' => 'fee_head_with_amount',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'user',
                'label' => trans('user.user'),
                'print_label' => 'user.profile.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'instrumentNumber',
                'label' => trans('finance.transaction.props.instrument_number'),
                'print_label' => 'payment.instrument_number',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'instrumentDate',
                'label' => trans('finance.transaction.props.instrument_date'),
                'print_label' => 'payment.instrument_date.formatted',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'clearingDate',
                'label' => trans('finance.transaction.props.clearing_date'),
                'print_label' => 'payment.clearing_date.formatted',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'bankDetail',
                'label' => trans('finance.transaction.props.bank_detail'),
                'print_label' => 'payment.bank_detail',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'branchDetail',
                'label' => trans('finance.transaction.props.branch_detail'),
                'print_label' => 'payment.branch_detail',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'cardProvider',
                'label' => trans('finance.transaction.props.card_provider'),
                'print_label' => 'payment.card_provider',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'referenceNumber',
                'label' => trans('finance.transaction.props.reference_number'),
                'print_label' => 'payment.reference_number',
                'sortable' => false,
                'visibility' => false,
            ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $ledgers = Str::toArray($request->query('ledgers'));
        $paymentMethods = Str::toArray($request->query('payment_methods'));

        return Transaction::query()
            ->select('transactions.id', 'transactions.uuid', 'transactions.number as serial_number', 'transactions.code_number as voucher_number', 'transactions.date', 'transactions.transactionable_type', 'transactions.head', 'transactions.type', 'transactions.amount', 'transactions.transactionable_id as student_id', 'transactions.is_online', 'transactions.payment_gateway', 'transactions.cancelled_at', 'transactions.rejected_at', 'transactions.processed_at', 'students.uuid as student_uuid', 'students.roll_number', 'students.batch_id', 'students.contact_id', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'), 'admissions.code_number', 'admissions.joining_date', 'admissions.leaving_date', 'batches.uuid as batch_uuid', 'batches.name as batch_name', 'courses.uuid as course_uuid', 'courses.name as course_name', 'contacts.father_name', 'contacts.contact_number', 'categories.name as category_name', 'registrations.uuid as registration_uuid', 'registrations.code_number as registration_code_number', 'registrations.date as registration_date', 'users.name as user_name')
            ->withPayment()
            ->with('records.model.installment.group', 'feePayments.head')
            ->whereIn('transactions.head', ['student_fee', 'registration_fee'])
            ->leftJoin('students', function ($join) {
                $join->on('transactions.transactionable_id', '=', 'students.id')
                    ->where('transactions.transactionable_type', '=', 'Student');
            })
            ->leftJoin('registrations', function ($join) {
                $join->on('transactions.transactionable_id', '=', 'registrations.id')
                    ->where('transactions.transactionable_type', '=', 'Registration');
            })
            ->leftJoin('contacts', function ($join) {
                $join->on('contacts.id', '=', \DB::raw("IF(transactions.transactionable_type = 'Student', students.contact_id, registrations.contact_id)"));
            })
            ->leftJoin('options as categories', function ($join) {
                $join->on('contacts.category_id', '=', 'categories.id');
            })
            ->leftJoin('admissions', 'students.admission_id', '=', 'admissions.id')
            ->leftJoin('batches', 'students.batch_id', '=', 'batches.id')
            ->leftJoin('courses', function ($join) {
                $join->on('courses.id', '=', \DB::raw("IF(transactions.transactionable_type = 'Student', batches.course_id, registrations.course_id)"));
            })
            ->leftJoin('users', function ($join) {
                $join->on('transactions.user_id', '=', 'users.id');
            })
            ->leftJoin('periods', 'transactions.period_id', '=', 'periods.id')
            ->where('periods.team_id', auth()->user()->current_team_id)
            ->when($ledgers, function ($q, $ledgers) {
                return $q->whereHas('payments', function ($q) use ($ledgers) {
                    $q->whereHas('ledger', function ($q) use ($ledgers) {
                        $q->whereIn('uuid', $ledgers);
                    });
                });
            })
            ->when($paymentMethods, function ($q, $paymentMethods) {
                return $q->whereHas('payments', function ($q) use ($paymentMethods) {
                    $q->whereHas('method', function ($q) use ($paymentMethods) {
                        $q->whereIn('uuid', $paymentMethods);
                    });
                });
            })
            ->when($request->query('pg_account'), function ($q, $pgAccount) {
                $q->where('payment_gateway->pg_account', $pgAccount);
            })
            ->when($request->query('status'), function ($q, $status) {
                if ($status == TransactionStatus::PENDING->value) {
                    $q->where(function ($q) {
                        $q->where('transactions.is_online', 1)->whereNull('processed_at')->where(function ($q) {
                            $q->whereNull('payment_gateway->status')->orWhere('payment_gateway->status', '!=', 'updated');
                        });
                    });
                } elseif ($status == TransactionStatus::FAILED->value) {
                    $q->where(function ($q) {
                        $q->where('transactions.is_online', 1)->whereNull('processed_at')->where('payment_gateway->status', '=', 'updated');
                    });
                } elseif ($status == TransactionStatus::SUCCEED->value) {
                    $q->where(function ($q) {
                        $q->where('transactions.is_online', 0)
                            ->orWhere(function ($q) {
                                $q->where('transactions.is_online', 1)->whereNotNull('processed_at');
                            });
                    })->whereNull('transactions.cancelled_at')->whereNull('transactions.rejected_at');
                } elseif ($status == TransactionStatus::CANCELLED->value) {
                    $q->whereNotNull('transactions.cancelled_at');
                } elseif ($status == TransactionStatus::REJECTED->value) {
                    $q->whereNotNull('transactions.rejected_at');
                }
            })
            ->when($request->query('name'), function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($request->query('category'), function ($q, $categoryUuid) {
                $q->where('categories.uuid', '=', $categoryUuid);
            })
            ->filter([
                'App\QueryFilters\ExactMatch:code_number,admissions.code_number',
                'App\QueryFilters\LikeMatch:voucher_number,transactions.code_number',
                'App\QueryFilters\WhereInMatch:batches.uuid,batches',
                'App\QueryFilters\DateBetween:start_date,end_date,transactions.date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $periodUuid = $request->query('period');
        $period = $periodUuid ? Period::query()
            ->whereUuid($periodUuid)->first() : null;

        $request->merge([
            'period_id' => $period?->id,
        ]);

        $ledgers = Str::toArray($request->query('ledgers'));
        $paymentMethods = Str::toArray($request->query('payment_methods'));

        $summary = Transaction::query()
            ->whereIn('transactions.head', ['student_fee', 'registration_fee'])
            ->leftJoin('students', function ($join) {
                $join->on('transactions.transactionable_id', '=', 'students.id')
                    ->where('transactions.transactionable_type', '=', 'Student');
            })
            ->leftJoin('registrations', function ($join) {
                $join->on('transactions.transactionable_id', '=', 'registrations.id')
                    ->where('transactions.transactionable_type', '=', 'Registration');
            })
            ->leftJoin('contacts', function ($join) {
                $join->on('contacts.id', '=', \DB::raw("IF(transactions.transactionable_type = 'Student', students.contact_id, registrations.contact_id)"));
            })
            ->leftJoin('options as categories', function ($join) {
                $join->on('contacts.category_id', '=', 'categories.id');
            })
            ->leftJoin('admissions', 'students.admission_id', '=', 'admissions.id')
            ->leftJoin('batches', 'students.batch_id', '=', 'batches.id')
            ->leftJoin('courses', function ($join) {
                $join->on('courses.id', '=', \DB::raw("IF(transactions.transactionable_type = 'Student', batches.course_id, registrations.course_id)"));
            })
            ->leftJoin('users', function ($join) {
                $join->on('transactions.user_id', '=', 'users.id');
            })
            ->leftJoin('periods', 'transactions.period_id', '=', 'periods.id')
            ->where('periods.team_id', auth()->user()->current_team_id)
            ->when($ledgers, function ($q, $ledgers) {
                return $q->whereHas('payments', function ($q) use ($ledgers) {
                    $q->whereHas('ledger', function ($q) use ($ledgers) {
                        $q->whereIn('uuid', $ledgers);
                    });
                });
            })
            ->when($paymentMethods, function ($q, $paymentMethods) {
                return $q->whereHas('payments', function ($q) use ($paymentMethods) {
                    $q->whereHas('method', function ($q) use ($paymentMethods) {
                        $q->whereIn('uuid', $paymentMethods);
                    });
                });
            })
            ->when($request->query('pg_account'), function ($q, $pgAccount) {
                $q->where('payment_gateway->pg_account', $pgAccount);
            })
            ->when($request->query('status'), function ($q, $status) {
                if ($status == TransactionStatus::PENDING->value) {
                    $q->where(function ($q) {
                        $q->where('transactions.is_online', 1)->whereNull('processed_at')->where(function ($q) {
                            $q->whereNull('payment_gateway->status')->orWhere('payment_gateway->status', '!=', 'updated');
                        });
                    });
                } elseif ($status == TransactionStatus::FAILED->value) {
                    $q->where(function ($q) {
                        $q->where('transactions.is_online', 1)->whereNull('processed_at')->where('payment_gateway->status', '=', 'updated');
                    });
                } elseif ($status == TransactionStatus::SUCCEED->value) {
                    $q->where(function ($q) {
                        $q->where('transactions.is_online', 0)
                            ->orWhere(function ($q) {
                                $q->where('transactions.is_online', 1)->whereNotNull('processed_at');
                            });
                    })->whereNull('transactions.cancelled_at')->whereNull('transactions.rejected_at');
                } elseif ($status == TransactionStatus::CANCELLED->value) {
                    $q->whereNotNull('transactions.cancelled_at');
                } elseif ($status == TransactionStatus::REJECTED->value) {
                    $q->whereNotNull('transactions.rejected_at');
                }
            })
            ->when($period, function ($q, $period) {
                $q->where('transactions.period_id', $period?->id);
            })
            ->selectRaw('SUM(transactions.amount) as total_fee')
            ->when($request->query('name'), function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($request->query('category'), function ($q, $categoryUuid) {
                $q->where('categories.uuid', '=', $categoryUuid);
            })
            ->filter([
                'App\QueryFilters\ExactMatch:code_number,admissions.code_number',
                'App\QueryFilters\LikeMatch:voucher_number,transactions.code_number',
                'App\QueryFilters\WhereInMatch:batches.uuid,batches',
                'App\QueryFilters\DateBetween:start_date,end_date,transactions.date',
            ])
            ->first();

        $sortBy = $this->getSort();

        $records = $this->filter($request)
            ->when($period, function ($q, $period) {
                $q->where('transactions.period_id', $period?->id);
            })
            ->when($sortBy == 'created_at', function ($q) {
                $q->orderBy('transactions.created_at', $this->getOrder());
            }, function ($q) {
                $q->orderBy($this->getSort(), $this->getOrder());
            })->when($request->query('output') == 'export_all_excel', function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            });

        return FeePaymentListResource::collection($records)
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'filename' => 'Fee Payment Report',
                    'sno' => $this->getSno(),
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                    'has_footer' => true,
                ],
                'footers' => [
                    ['key' => 'voucherNumber', 'label' => trans('general.total')],
                    ['key' => 'name', 'label' => ''],
                    ['key' => 'fatherName', 'label' => ''],
                    ['key' => 'course', 'label' => ''],
                    ['key' => 'amount', 'label' => \Price::from($summary->total_fee)->formatted],
                    ['key' => 'date', 'label' => ''],
                    ['key' => 'ledger', 'label' => ''],
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
