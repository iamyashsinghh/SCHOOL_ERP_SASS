<?php

namespace App\Services\Finance\PaymentGateway;

use App\Concerns\HasPaymentGateway;
use App\Contracts\Finance\PaymentGateway;
use App\Helpers\SysHelper;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class Amwalpay implements PaymentGateway
{
    use HasPaymentGateway;

    public function getName(): string
    {
        return 'amwalpay';
    }

    public function getVersion(): string
    {
        return config('config.finance.amwalpay_version', 'NA');
    }

    public function isEnabled(): void
    {
        if (! config('config.finance.enable_amwalpay', false)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_operation')]);
        }
    }

    public function getMultiplier(Request $request): float
    {
        return 1;
    }

    public function supportedCurrencies(): array
    {
        return [];
    }

    public function unsupportedCurrencies(): array
    {
        return [];
    }

    public function initiatePayment(Request $request, Student $student, Transaction $transaction): array
    {
        $this->validateCurrency($transaction, $this->supportedCurrencies(), $this->unsupportedCurrencies());

        $amount = SysHelper::formatAmount($transaction->amount->value * $this->getMultiplier($request), $transaction->currency);

        $datetime = now()->format('Y-m-d\TH:i:s\Z');

        $data = [
            'Amount' => $amount,
            'CurrencyId' => 512,
            'MerchantId' => config('config.finance.amwalpay_client'),
            'MerchantReference' => Arr::get($transaction->payment_gateway, 'reference_number'),
            'RequestDateTime' => $datetime,
            'SessionToken' => '',
            'TerminalId' => config('config.finance.amwalpay_secret'),
        ];

        $inputKey = collect($data)
            ->sortKeys()
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode('&');

        $binaryKey = hex2bin(config('config.finance.amwalpay_signature'));
        $hash = hash_hmac('sha256', $inputKey, $binaryKey);

        $params = [
            'MID' => config('config.finance.amwalpay_client'),
            'TID' => config('config.finance.amwalpay_secret'),
            'CurrencyId' => 512,
            'AmountTrxn' => $amount,
            'MerchantReference' => Arr::get($transaction->payment_gateway, 'reference_number'),
            'LanguageId' => 'en',
            'SecureHash' => $hash,
            'TrxDateTime' => $datetime,
            'PaymentViewType' => 1,
            'RequestSource' => 'Checkout_Direct_Integration',
            'SessionToken' => '',
        ];

        return [
            'params' => $params,
            'token' => $transaction->uuid,
        ];
    }

    private function getTransaction(Request $request)
    {
        $transaction = Transaction::query()
            ->whereUuid($request->token)
            ->first();

        if (! $transaction) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        return $transaction;
    }

    public function confirmPayment(Request $request): Transaction
    {
        // logger($request->all());

        $transaction = $this->getTransaction($request);

        $integrityParametrs = [
            'amount' => Arr::get($request->payment, 'data.amount'),
            'currencyId' => Arr::get($request->payment, 'data.currency_id'),
            'customerId' => Arr::get($request->payment, 'data.customer_id'),
            'customerTokenId' => Arr::get($request->payment, 'data.customer_token_id'),
            'merchantId' => Arr::get($request->payment, 'data.merchant_id'),
            'merchantReference' => Arr::get($request->payment, 'data.merchant_reference'),
            'responseCode' => Arr::get($request->payment, 'data.response_code'),
            'terminalId' => Arr::get($request->payment, 'data.terminal_id'),
            'transactionId' => Arr::get($request->payment, 'data.transaction_id'),
            'transactionTime' => Arr::get($request->payment, 'data.transaction_time'),
        ];

        // logger($integrityParametrs);

        $inputKey = collect($integrityParametrs)
            ->sortKeys()
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode('&');

        // logger($inputKey);

        $binaryKey = hex2bin(config('config.finance.amwalpay_signature'));
        $hash = hash_hmac('sha256', $inputKey, $binaryKey);
        // logger($hash);

        // logger(Arr::get($request->payment, "data.secure_hash_value"));

        // if (! hash_equals($hash, Arr::get($request->payment, "data.secure_hash_value"))) {
        //     throw ValidationException::withMessages(['message' => 'error_in_integrity']);
        // }

        if (Arr::get($request->payment, 'data.response_code') != '00') {
            throw ValidationException::withMessages(['message' => trans('finance.payment_failed')]);
        }

        if (Arr::get($request->payment, 'data.merchant_reference') != Arr::get($transaction->payment_gateway, 'reference_number')) {
            throw ValidationException::withMessages(['message' => trans('finance.payment_failed')]);
        }

        if (Arr::get($request->payment, 'data.amount') != $transaction->amount->value) {
            throw ValidationException::withMessages(['message' => trans('finance.payment_failed')]);
        }

        return $transaction;
    }

    public function failPayment(Request $request): Transaction
    {
        $transaction = $this->getTransaction($request);

        $failedLogs = $transaction->failed_logs;
        $failedLogs[] = [
            'name' => $this->getName(),
            'error' => $request->error,
            'failed_at' => now()->toDateTimeString(),
        ];
        $transaction->failed_logs = $failedLogs;
        $transaction->save();

        return $transaction;
    }
}
