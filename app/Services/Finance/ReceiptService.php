<?php

namespace App\Services\Finance;

use App\Actions\Finance\CheckTransactionEligibility;
use App\Enums\Finance\TransactionStatus;
use App\Enums\OptionType;
use App\Http\Resources\Academic\PeriodResource;
use App\Http\Resources\Finance\LedgerResource;
use App\Http\Resources\Finance\PaymentMethodResource;
use App\Http\Resources\OptionResource;
use App\Models\Academic\Period;
use App\Models\Finance\Ledger;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\Receipt;
use App\Models\Finance\Transaction;
use App\Models\Finance\TransactionPayment;
use App\Models\Finance\TransactionRecord;
use App\Models\Option;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    use FormatCodeNumber;

    private function codeNumber(array $params = []): array
    {
        $numberPrefix = config('config.finance.receipt_number_prefix');
        $numberSuffix = config('config.finance.receipt_number_suffix');
        $digit = config('config.finance.receipt_number_digit');

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat, Arr::get($params, 'date'));

        $shortcode = [
            'payment_method' => Arr::get($params, 'payment_method'),
            'ledger' => Arr::get($params, 'ledger'),
        ];

        $numberFormat = $this->preFormatForTransaction($numberFormat, $shortcode);

        $codeNumber = (int) Transaction::query()
            ->join('periods', 'periods.id', '=', 'transactions.period_id')
            ->where('periods.team_id', auth()->user()->current_team_id)
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(): array
    {
        $types = [
            ['label' => trans('student.student'), 'value' => 'student'],
            ['label' => trans('employee.employee'), 'value' => 'employee'],
            ['label' => trans('general.other'), 'value' => 'other'],
        ];

        $categories = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TRANSACTION_CATEGORY->value)
            ->get());

        $paymentMethods = PaymentMethodResource::collection(PaymentMethod::query()
            ->byTeam()
            ->where('is_payment_gateway', false)
            ->get());

        $onlinePaymentMethods = PaymentMethodResource::collection(PaymentMethod::query()
            ->byTeam()
            ->where('is_payment_gateway', true)
            ->get());

        $primaryLedgers = LedgerResource::collection(Ledger::query()
            ->byTeam()
            ->subType('primary')
            ->get());

        $secondaryLedgers = LedgerResource::collection(Ledger::query()
            ->byTeam()
            ->subType('income')
            ->get());

        $statuses = TransactionStatus::getOptions();

        $periods = PeriodResource::collection(Period::query()
            ->with('session')
            ->byTeam()
            ->get());

        $bankNames = Option::query()
            ->where('type', OptionType::BANK_NAME->value)
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->name,
                ];
            });

        $cardProviders = Option::query()
            ->where('type', OptionType::CARD_PROVIDER->value)
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->name,
                ];
            });

        return compact('types', 'categories', 'paymentMethods', 'onlinePaymentMethods', 'primaryLedgers', 'secondaryLedgers', 'statuses', 'periods', 'bankNames', 'cardProviders');
    }

    public function create(Request $request): Receipt
    {
        (new CheckTransactionEligibility)->execute();

        \DB::beginTransaction();

        $receipt = Receipt::forceCreate($this->formatParams($request));

        $this->updatePayments($request, $receipt);

        $this->updateRecords($request, $receipt);

        \DB::commit();

        $receipt->addMedia($request);

        return $receipt;
    }

    private function formatParams(Request $request, ?Receipt $receipt = null): array
    {
        $primaryLedger = $request->primary_ledger;

        $transactionableType = null;
        $transactionableId = null;
        if ($request->type == 'student') {
            $transactionableType = 'Student';
            $transactionableId = $request->student_id;
        } elseif ($request->type == 'employee') {
            $transactionableType = 'Employee';
            $transactionableId = $request->employee_id;
        }

        $formatted = [
            'type' => 'receipt',
            'date' => $request->date,
            'amount' => $request->amount,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'transactionable_type' => $transactionableType,
            'transactionable_id' => $transactionableId,
            'remarks' => $request->remarks,
        ];

        if (! $receipt) {
            $codeNumberDetail = $this->codeNumber([
                'payment_method' => $request->payment_method_code,
                'ledger' => $request->ledger_code,
                'date' => $request->date,
            ]);

            $formatted['number_format'] = Arr::get($codeNumberDetail, 'number_format');
            $formatted['number'] = Arr::get($codeNumberDetail, 'number');
            $formatted['code_number'] = Arr::get($codeNumberDetail, 'code_number');
            $formatted['period_id'] = auth()->user()->current_period_id;
            $formatted['user_id'] = auth()->id();
        }

        $meta = $receipt?->meta ?? [];
        $meta['sub_head'] = $request->type;

        if ($request->type == 'other') {
            $meta['name'] = $request->name;
            $meta['contact_number'] = $request->contact_number;
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function updatePayments(Request $request, Receipt $receipt, string $action = 'create'): void
    {
        $paymentMethodIds = [];
        foreach ($request->payment_methods as $paymentMethod) {
            $paymentMethodIds[] = Arr::get($paymentMethod, 'payment_method_id');

            $transactionPayment = TransactionPayment::firstOrCreate([
                'transaction_id' => $receipt->id,
                'payment_method_id' => Arr::get($paymentMethod, 'payment_method_id'),
            ]);

            $transactionPayment->ledger_id = $request->primary_ledger->id;
            $transactionPayment->amount = Arr::get($paymentMethod, 'amount', 0);
            $transactionPayment->details = Arr::get($paymentMethod, 'details', []);
            $transactionPayment->save();
        }

        TransactionPayment::query()
            ->whereTransactionId($receipt->id)
            ->whereNotIn('payment_method_id', $paymentMethodIds)
            ->delete();

        $receipt->refresh();
        $receipt->load('payments.ledger');

        foreach ($receipt->payments as $payment) {
            $primaryLedger = $payment->ledger;
            $primaryLedger->updatePrimaryBalance($receipt->type, $payment->amount->value);
        }
    }

    private function updateRecords(Request $request, Receipt $receipt, string $action = 'create'): void
    {
        $ledgerIds = [];
        $transactionRecord = TransactionRecord::firstOrCreate([
            'transaction_id' => $receipt->id,
            'ledger_id' => $request->secondary_ledger->id,
        ]);

        $ledgerIds[] = $request->secondary_ledger->id;

        $transactionRecord->amount = $request->amount;
        $transactionRecord->remarks = $request->remarks;
        $transactionRecord->save();

        TransactionRecord::query()
            ->whereTransactionId($receipt->id)
            ->whereNotIn('ledger_id', $ledgerIds)
            ->delete();

        $receipt->refresh();
        $receipt->load('records.ledger');

        foreach ($receipt->records as $record) {
            $secondaryLedger = $record->ledger;
            $secondaryLedger->updateSecondaryBalance($receipt->type, $record->amount->value);
        }
    }

    public function update(Receipt $receipt, Request $request): void
    {
        (new CheckTransactionEligibility)->execute();

        if (! $receipt->isEditable()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        if ($receipt->head) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        \DB::beginTransaction();

        $receipt->load('payments.ledger', 'records.ledger');

        foreach ($receipt->payments as $payment) {
            $previousLedger = $payment->ledger;
            $previousLedger->reversePrimaryBalance($receipt->type, $payment->amount->value);
        }

        foreach ($receipt->records as $record) {
            $previousLedger = $record->ledger;
            $previousLedger->reverseSecondaryBalance($receipt->type, $record->amount->value);
        }

        $receipt->forceFill($this->formatParams($request, $receipt))->save();

        $this->updatePayments($request, $receipt, 'update');

        $this->updateRecords($request, $receipt, 'udpate');

        $receipt->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Receipt $receipt): void
    {
        if (! $receipt->isEditable()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function delete(Receipt $receipt): void
    {
        \DB::beginTransaction();

        $receipt->load('payments.ledger', 'records.ledger');

        foreach ($receipt->payments as $payment) {
            $previousLedger = $payment->ledger;
            $previousLedger->reversePrimaryBalance($receipt->type, $payment->amount->value);
        }

        foreach ($receipt->records as $record) {
            $secondaryLedger = $record->ledger;
            $secondaryLedger->reverseSecondaryBalance($receipt->type, $record->amount->value);
        }

        $receipt->delete();

        \DB::commit();
    }
}
