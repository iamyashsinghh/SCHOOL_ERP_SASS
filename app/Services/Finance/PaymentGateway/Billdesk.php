<?php

namespace App\Services\Finance\PaymentGateway;

use App\Concerns\HasPaymentGateway;
use App\Contracts\Finance\PaymentGateway;
use App\Models\Finance\Transaction;
use App\Models\Student\Student;
use App\Support\PaymentGatewayMultiAccountSeparator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Billdesk implements PaymentGateway
{
    use HasPaymentGateway, PaymentGatewayMultiAccountSeparator;

    public function getName(): string
    {
        return 'billdesk';
    }

    public function getVersion(): string
    {
        return config('config.finance.billdesk_version', 'NA');
    }

    public function isEnabled(): void
    {
        if (! config('config.finance.enable_billdesk', false)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_operation')]);
        }
    }

    public function getMultiplier(Request $request): float
    {
        return 1;
    }

    public function supportedCurrencies(): array
    {
        return ['INR'];
    }

    public function unsupportedCurrencies(): array
    {
        return [];
    }

    public function initiatePayment(Request $request, Student $student, Transaction $transaction): array
    {
        $this->validateCurrency($transaction, $this->supportedCurrencies(), $this->unsupportedCurrencies());

        if (in_array(config('config.finance.billdesk_version'), ['1.0', 1.0])) {
            return $this->getV10Data($student, $transaction);
        } elseif (in_array(config('config.finance.billdesk_version'), ['1.2', '1.5', 1.2, 1.5])) {
            return $this->getV125Data($student, $transaction);
        }

        throw ValidationException::withMessages(['message' => trans('general.errors.invalid_operation')]);
    }

    private function getV10Data(Student $student, Transaction $transaction)
    {
        $pgAccount = Arr::get($transaction->payment_gateway, 'pg_account');

        $secret = $this->getCredential(config('config.finance.billdesk_secret'), $pgAccount);
        $client = $this->getCredential(config('config.finance.billdesk_client'), $pgAccount);
        $merchantId = $this->getCredential(config('config.finance.billdesk_merchant'), $pgAccount);

        $pgAccount = 'NA';
        $part1 = '|NA|'.$transaction->amount->value.'|NA|NA|NA|INR|DIRECT|R|';
        $part2 = '|NA|NA|F|'.config('config.general.app_email').'|'.config('config.general.app_phone').'|NA|NA|NA|NA|'.$pgAccount.'|'.url('/payment/billdesk/response');

        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');

        $str = $merchantId.'|'.$referenceNumber.$part1.$client.$part2;

        $checksum = hash_hmac('sha256', $str, $secret, false);

        $msg = $str.'|'.strtoupper($checksum);

        return [
            'amount' => $transaction->amount,
            'version' => config('config.finance.billdesk_version'),
            'token' => $transaction->uuid,
            'msg' => $msg,
            'reference_number' => $referenceNumber,
            'pg_url' => 'https://pgi.billdesk.com/pgidsk/PGIMerchantPayment',
            'return_url' => url('/payment/billdesk/response'),
        ];
    }

    private function getV125Data(Student $student, Transaction $transaction)
    {
        $pgAccount = Arr::get($transaction->payment_gateway, 'pg_account');

        $secret = $this->getCredential(config('config.finance.billdesk_secret'), $pgAccount);
        $client = $this->getCredential(config('config.finance.billdesk_client'), $pgAccount);
        $merchantId = $this->getCredential(config('config.finance.billdesk_merchant'), $pgAccount);

        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');

        $returnUrl = Arr::get($transaction->payment_gateway, 'return_url', url('/payment/billdesk/response/'.$referenceNumber));

        $data = [
            'mercid' => $merchantId,
            'orderid' => $referenceNumber,
            'amount' => $transaction->amount->value,
            'order_date' => date_format(new \DateTime, DATE_W3C),
            'currency' => '356',
            'ru' => $returnUrl,
            // 'cancel_url' => url('payment/billdesk/cancel'),
            'additional_info' => [
                'additional_info1' => 'student_fee',
                'additional_info2' => $transaction->uuid,
                'additional_info3' => Str::toWord($student->code_number),
                'additional_info4' => Str::toWord($student->name),
                'additional_info5' => Str::toWord($student->course_name.' '.$student->batch_name),
                'additional_info7' => 'NA',
            ],
            'itemcode' => 'DIRECT',
            'device' => [
                'init_channel' => 'internet',
                'ip' => request()->getClientIp(),
                'user_agent' => request()->header('User-Agent'),
            ],
        ];

        if ($pgAccount) {
            // $data['settlement_lob'] = $pgAccount;
            $data['additional_info']['additional_info7'] = $pgAccount;
        }

        // logger($data);

        $childWindow = true;
        $pgUrl = 'https://pguat.billdesk.io/payments/ve1_2/orders/create';

        if (in_array(config('config.finance.billdesk_version'), ['1.2', '1.5'])) {
            $childWindow = false;
            $pgUrl = 'https://uat1.billdesk.com/u2/payments/ve1_2/orders/create';
        }

        if (config('config.finance.enable_live_billdesk_mode')) {
            $pgUrl = 'https://api.billdesk.com/payments/ve1_2/orders/create';
        }

        $encodedPayload = JWT::encode($data, $secret, 'HS256', null, [
            'alg' => 'HS256',
            'clientid' => $client,
        ]);

        $traceId = strtoupper(Str::random(10));
        $time = time();

        // logger('Merchant ID: '.$merchantId);
        // logger('Client ID: '.$client);
        // logger('PG URL: '.$pgUrl);
        // logger('Trace ID: '.$traceId);
        // logger('Timestamp: '.$time);

        // logger('Encoded Payload:');
        // logger($encodedPayload);

        $pgResponse = Http::withHeaders([
            'Content-type' => 'application/jose',
            'accept' => 'application/jose',
            'BD-Traceid' => $traceId,
            'BD-Timestamp' => $time,
        ])->withBody($encodedPayload, 'application/jose')->post($pgUrl);

        // logger('PG Response:');
        // logger($pgResponse);

        $decodedResponse = (array) JWT::decode($pgResponse->body(), new Key($secret, 'HS256'));

        // logger('Decoded Payload:');
        // logger($decodedResponse);

        if (Arr::get($decodedResponse, 'status') != 'ACTIVE') {
            throw ValidationException::withMessages(['message' => trans('student.payment.invalid_signature')]);
        }

        $links = Arr::get($decodedResponse, 'links', []);
        $bdOrderId = $links[1]?->parameters?->bdorderid ?? null;
        $authToken = $links[1]?->headers?->authorization ?? null;

        if (empty($bdOrderId) || empty($authToken)) {
            throw ValidationException::withMessages(['message' => 'Invalid PG response']);
        }

        return [
            'merchant_id' => $merchantId,
            'bd_order_id' => $bdOrderId,
            'auth_token' => $authToken,
            'token' => $transaction->uuid,
            'return_url' => $returnUrl,
            'child_window' => $childWindow,
            'retry_count' => 3,
            'payment_options' => ['card', 'emi', 'nb', 'upi', 'wallets', 'qr', 'gpay'],
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
        $transaction = $this->getTransaction($request);

        throw ValidationException::withMessages(['message' => 'test']);

        return $transaction;
    }

    public function failPayment(Request $request): Transaction
    {
        $transaction = $this->getTransaction($request);

        $failedLogs = $transaction->failed_logs;
        $failedLogs[] = [
            'name' => $this->getName(),
            'error' => $request->error,
        ];
        $transaction->failed_logs = $failedLogs;
        $transaction->save();

        return $transaction;
    }
}
