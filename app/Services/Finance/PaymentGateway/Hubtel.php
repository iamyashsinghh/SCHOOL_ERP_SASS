<?php

namespace App\Services\Finance\PaymentGateway;

use App\Concerns\HasPaymentGateway;
use App\Contracts\Finance\PaymentGateway;
use App\Helpers\SysHelper;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Hubtel implements PaymentGateway
{
    use HasPaymentGateway;

    public function getName(): string
    {
        return 'hubtel';
    }

    public function getVersion(): string
    {
        return config('config.finance.hubtel_version', 'NA');
    }

    public function isEnabled(): void
    {
        if (! config('config.finance.enable_hubtel', false)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_operation')]);
        }
    }

    public function getMultiplier(Request $request): float
    {
        return 1;
    }

    public function supportedCurrencies(): array
    {
        return ['NGN', 'GHS'];
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
            'totalAmount' => $amount,
            'description' => $student->name.' '.$student->course_name.' '.$student->batch_name,
            'callbackUrl' => url('payment/hubtel/callback'),
            'returnUrl' => url('payment/hubtel/response'),
            'merchantAccountNumber' => config('config.finance.hubtel_merchant'),
            'cancellationUrl' => url('payment/hubtel/cancel?reference_number='.Arr::get($transaction->payment_gateway, 'reference_number')),
            'clientReference' => Arr::get($transaction->payment_gateway, 'reference_number'),
        ];

        $basicAuth = base64_encode(config('config.finance.hubtel_client').':'.config('config.finance.hubtel_secret'));

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
            'Authorization' => 'Basic '.$basicAuth,
        ])
            ->post('https://payproxyapi.hubtel.com/items/initiate', $data);

        if (! in_array($response->status(), [200])) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $response = $response->json();

        if (Arr::get($response, 'responseCode') != '0000' || Arr::get($response, 'status') != 'Success') {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $paymentGateway = $transaction->payment_gateway;
        $paymentGateway['payment_intent_id'] = Arr::get($response, 'data.checkoutId');
        $transaction->payment_gateway = $paymentGateway;
        $transaction->save();

        return [
            'pg_url' => Arr::get($response, 'data.checkoutUrl'),
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
        throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
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
