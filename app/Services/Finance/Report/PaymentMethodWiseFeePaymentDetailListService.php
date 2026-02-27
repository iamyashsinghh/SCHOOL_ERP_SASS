<?php

namespace App\Services\Finance\Report;

use App\Contracts\ListGenerator;
use App\Helpers\CalHelper;
use App\Models\Academic\Period;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\Transaction;
use App\Models\Student\Registration;
use App\Models\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentMethodWiseFeePaymentDetailListService extends ListGenerator
{
    protected $allowedSorts = ['date', 'total'];

    protected $defaultSort = 'date';

    protected $defaultOrder = 'desc';

    public function getHeaders(Collection $paymentMethods): array
    {
        $headers = [];

        return $headers;
    }

    public function filter(Request $request, Collection $paymentMethods): Builder
    {
        $startDate = $request->query('startDate', today()->subWeek(1)->toDateString());
        $endDate = $request->query('endDate', today()->toDateString());

        if (! CalHelper::validateDate($startDate)) {
            throw ValidationException::withMessages(['message' => trans('validation.date', ['attribute' => trans('general.start_date')])]);
        }

        if (! CalHelper::validateDate($endDate)) {
            throw ValidationException::withMessages(['message' => trans('validation.date', ['attribute' => trans('general.end_date')])]);
        }

        if ($startDate > $endDate) {
            $startDate = $request->query('endDate');
            $endDate = $request->query('startDate');
        }

        $ledgers = Str::toArray($request->query('ledgers'));

        return Transaction::query()
            ->select('transactions.*', 'transaction_payments.details as payment_details', 'transaction_payments.amount as payment_amount', 'ledgers.name as ledger_name', 'payment_methods.name as payment_method_name', 'users.name as processed_by')
            ->when($request->period_id, function ($q, $periodId) {
                $q->byPeriod($periodId);
            })
            ->leftJoin('transaction_payments', 'transactions.id', '=', 'transaction_payments.transaction_id')
            ->leftJoin('ledgers', 'transaction_payments.ledger_id', '=', 'ledgers.id')
            ->leftJoin('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->leftJoin('users', 'transactions.user_id', '=', 'users.id')
            ->leftJoin('periods', 'transactions.period_id', '=', 'periods.id')
            ->where('periods.team_id', auth()->user()->current_team_id)
            ->whereIn('transactions.head', ['student_fee', 'registration_fee'])
            ->when($ledgers, function ($q, $ledgers) {
                $q->whereHas('payments', function ($q) use ($ledgers) {
                    $q->whereHas('ledger', function ($q) use ($ledgers) {
                        $q->whereIn('uuid', $ledgers);
                    });
                });
            })
            ->whereNull('transactions.cancelled_at')
            ->whereNull('rejected_at')
            ->where(function ($q) {
                $q->where('is_online', '!=', true)
                    ->orWhere(function ($q) {
                        $q->where('is_online', '=', true)
                            ->whereNotNull('processed_at');
                    });
            })
            ->when($request->query('pg_account'), function ($q, $pgAccount) {
                $q->where('payment_gateway->pg_account', $pgAccount);
            })
            ->whereBetween('date', [$startDate, $endDate]);
    }

    public function paginate(Request $request): array
    {
        $periodUuid = $request->query('period');
        $period = $periodUuid ? Period::query()
            ->whereUuid($periodUuid)->first() : null;

        $request->merge([
            'period_id' => $period?->id,
        ]);

        $paymentMethods = PaymentMethod::query()
            ->byTeam()
            ->get();

        $request->merge([
            'payment_method_slugs' => $paymentMethods->pluck('slug')->toArray(),
        ]);

        $startDate = $request->query('startDate', today()->subWeek(1)->toDateString());
        $endDate = $request->query('endDate', today()->toDateString());

        $ledgers = Str::toArray($request->query('ledgers'));

        $transactions = $this->filter($request, $paymentMethods)
            ->orderBy($this->getSort(), $this->getOrder())
            ->get();

        $students = Student::query()
            ->summary()
            ->whereIn('students.id', $transactions->filter(function ($transaction) {
                return $transaction->transactionable_type == 'Student';
            })->pluck('transactionable_id'))
            ->get();

        $registrations = Registration::query()
            ->select('registrations.*', 'courses.name as course_name', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'))
            ->join('contacts', 'registrations.contact_id', '=', 'contacts.id')
            ->join('courses', 'registrations.course_id', '=', 'courses.id')
            ->whereIn('registrations.id', $transactions->filter(function ($transaction) {
                return $transaction->transactionable_type == 'Registration';
            })->pluck('transactionable_id'))
            ->get();

        $paymentMethodWiseTransactions = $transactions->groupBy('payment_method_name');

        $rows = [];

        foreach ($paymentMethodWiseTransactions as $paymentMethodName => $paymentMethodWiseTransaction) {
            $row = [];

            $header = [
                trans('general.sno'),
                trans('student.props.name'),
                trans('student.admission.props.code_number'),
                trans('academic.course.course'),
                trans('finance.transaction.props.date'),
                trans('finance.transaction.props.code_number'),
                trans('finance.transaction.props.amount'),
                trans('user.user'),
            ];

            $paymentMethod = $paymentMethods->firstWhere('name', $paymentMethodName);

            if ($paymentMethod->getConfig('has_instrument_number')) {
                array_push($header, trans('finance.transaction.props.instrument_number'));
            }

            if ($paymentMethod->getConfig('has_instrument_date')) {
                array_push($header, trans('finance.transaction.props.instrument_date'));
            }

            if ($paymentMethod->getConfig('has_clearing_date')) {
                array_push($header, trans('finance.transaction.props.clearing_date'));
            }

            if ($paymentMethod->getConfig('has_bank_detail')) {
                array_push($header, trans('finance.transaction.props.bank_detail'));
            }

            if ($paymentMethod->getConfig('has_branch_detail')) {
                array_push($header, trans('finance.transaction.props.branch_detail'));
            }

            if ($paymentMethod->getConfig('has_reference_number')) {
                array_push($header, trans('finance.transaction.props.reference_number'));
            }

            if ($paymentMethod->getConfig('has_card_provider')) {
                array_push($header, trans('finance.transaction.props.card_provider'));
            }

            $sno = 1;
            $total = 0;
            foreach ($paymentMethodWiseTransaction as $transaction) {
                $name = null;
                $codeNumber = null;
                $courseBatch = null;
                if ($transaction->transactionable_type == 'Student') {
                    $student = $students->firstWhere('id', $transaction->transactionable_id);
                    $name = $student?->name;
                    $codeNumber = $student?->code_number;
                    $courseBatch = $student?->course_name.' '.$student?->batch_name;
                } elseif ($transaction->transactionable_type == 'Registration') {
                    $registration = $registrations->firstWhere('id', $transaction->transactionable_id);
                    $name = $registration?->name;
                    $codeNumber = $registration?->code_number;
                    $courseBatch = $registration?->course_name;
                }

                $data = [
                    $sno++,
                    $name,
                    $codeNumber,
                    $courseBatch,
                    $transaction->date->formatted,
                    $transaction->code_number,
                    \Price::from($transaction->payment_amount)->formatted,
                    $transaction->processed_by,
                ];

                $total += $transaction->payment_amount;

                $paymentDetails = json_decode($transaction->payment_details, true);

                if ($paymentMethod->getConfig('has_instrument_number')) {
                    array_push($data, Arr::get($paymentDetails, 'instrument_number'));
                }

                if ($paymentMethod->getConfig('has_instrument_date')) {
                    array_push($data, \Cal::date(Arr::get($paymentDetails, 'instrument_date'))?->formatted);
                }

                if ($paymentMethod->getConfig('has_clearing_date')) {
                    array_push($data, \Cal::date(Arr::get($paymentDetails, 'clearing_date'))?->formatted);
                }

                if ($paymentMethod->getConfig('has_bank_detail')) {
                    array_push($data, Arr::get($paymentDetails, 'bank_detail'));
                }

                if ($paymentMethod->getConfig('has_branch_detail')) {
                    array_push($data, Arr::get($paymentDetails, 'branch_detail'));
                }

                if ($paymentMethod->getConfig('has_reference_number')) {
                    array_push($data, Arr::get($paymentDetails, 'reference_number'));
                }

                if ($paymentMethod->getConfig('has_card_provider')) {
                    array_push($data, Arr::get($paymentDetails, 'card_provider'));
                }

                $row[] = $data;
            }

            if ($data) {
                $rows[] = [
                    'name' => $paymentMethodName,
                    'headers' => $header,
                    'data' => $row,
                    'footer' => [
                        trans('general.total'),
                        '',
                        '',
                        '',
                        '',
                        '',
                        \Price::from($total)->formatted,
                    ],
                ];
            }
        }

        return $rows;
    }

    public function list(Request $request): array
    {
        return $this->paginate($request);
    }
}
