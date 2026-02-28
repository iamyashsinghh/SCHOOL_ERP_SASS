<?php

namespace App\Services\PaymentGateway;

use App\Actions\Student\PayOnlineFee;
use App\Enums\Finance\PaymentStatus;
use App\Helpers\SysHelper;
use App\Models\Tenant\Config\Config;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Student\Registration;
use App\Models\Tenant\Student\Student;
use App\Support\PaymentGatewayMultiAccountSeparator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BilldeskService
{
    use PaymentGatewayMultiAccountSeparator;

    public function checkStatus(Request $request)
    {
        if (empty($request->query('reference_number'))) {
            return 'Enter reference number';
        }

        $transaction = Transaction::query()
            ->with('period')
            ->where('payment_gateway->reference_number', $request->query('reference_number'))
            ->first();

        if (! $transaction) {
            abort(404);
        }

        $pgAccount = Arr::get($transaction->payment_gateway, 'pg_account');

        $secret = $this->getCredential(config('config.finance.billdesk_secret'), $pgAccount);
        $client = $this->getCredential(config('config.finance.billdesk_client'), $pgAccount);
        $merchantId = $this->getCredential(config('config.finance.billdesk_merchant'), $pgAccount);
        $mode = (bool) config('config.finance.enable_live_billdesk_mode');
        $version = config('config.finance.billdesk_version');

        $data = [
            'mercid' => $merchantId,
            'orderid' => $request->query('reference_number'),
            'refund_details' => true,
        ];

        $encodedPayload = JWT::encode($data, $secret, 'HS256', null, [
            'alg' => 'HS256',
            'clientid' => $client,
        ]);

        $traceId = strtoupper(Str::random(10));
        $time = time();

        $billdeskUrl = 'https://pguat.billdesk.io';

        if (in_array(config('config.finance.billdesk_version'), ['1.2', '1.5'])) {
            $billdeskUrl = 'https://uat1.billdesk.com/u2';
        }

        if ($mode) {
            $billdeskUrl = 'https://api.billdesk.com';
        }

        $pgResponse = Http::withHeaders([
            'Content-type' => 'application/jose',
            'accept' => 'application/jose',
            'BD-Traceid' => $traceId,
            'BD-Timestamp' => $time,
        ])->withBody($encodedPayload, 'application/jose')->post($billdeskUrl.'/payments/ve1_2/transactions/get');

        $response = (array) JWT::decode($pgResponse->getBody(), new Key($secret, 'HS256'));

        dd($response);
    }

    public function getResponse(Request $request, string $referenceNumber)
    {
        $params = $request->all();

        // get reference number from URL
        // $referenceNumber = Arr::get($params, 'orderid');

        $transaction = Transaction::query()
            ->with('period')
            ->where('payment_gateway->reference_number', $referenceNumber)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->first();

        if (! $transaction) {
            abort(404);
        }

        $config = Config::query()
            ->where('team_id', $transaction->period->team_id)
            ->whereName('finance')
            ->first();

        $pgVersion = Arr::get($transaction->payment_gateway, 'version');

        $head = $transaction->head;

        $student = $transaction?->transactionable;
        $studentUrl = null;

        if ($head == 'student_fee') {
            $studentUrl = url('/app/students/'.$student?->uuid.'/fee');
        } else if ($head == 'registration_fee') {
            $studentUrl = url('/app/student/registrations/'.$student?->uuid);
        }

        if (! in_array($pgVersion, ['1.2', '1.5', 1.2, 1.5])) {
            return view('messages.student.payment', [
                'message' => trans('finance.pg_version_mismatch', ['attribute' => $referenceNumber]),
                'url' => route('app'),
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => trans('global.go_to', ['attribute' => trans('dashboard.dashboard')]),
            ]);
        }

        $pgAccount = Arr::get($transaction->payment_gateway, 'pg_account');

        $secret = $this->getCredential(Arr::get($config->value, 'billdesk_secret'), $pgAccount);

        $transactionResponse = Arr::get($request->all(), 'transaction_response');

        if (empty($transactionResponse)) {
            return view('messages.student.payment', [
                'message' => trans('finance.no_response_received', ['attribute' => $referenceNumber]),
                'url' => route('app'),
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => trans('global.go_to', ['attribute' => trans('dashboard.dashboard')]),
            ]);
        }

        $response = (array) JWT::decode($transactionResponse, new Key($secret, 'HS256'));

        $additionalInfo = (array) Arr::get($response, 'additional_info', []);

        $referenceNumber = Arr::get($response, 'orderid');

        $transaction->payment_gateway = array_merge($transaction->payment_gateway, [
            'transactionid' => Arr::get($response, 'transactionid'),
            'bankid' => Arr::get($response, 'bankid'),
            'payment_method_type' => Arr::get($response, 'payment_method_type'),
        ]);
        $transaction->save();

        $paymentType = Arr::get($additionalInfo, 'additional_info1');
        $transactionUuid = Arr::get($additionalInfo, 'additional_info2');

        $url = route('app');
        $actionText = trans('global.go_to', ['attribute' => trans('dashboard.dashboard')]);

        if ($head == 'student_fee') {
            $student = Student::find($transaction->transactionable_id);
            $url = url("app/students/{$student->uuid}/fee");
            $actionText = trans('global.go_to', ['attribute' => trans('student.fee.fee')]);

            if (empty($transaction->user_id)) {
                $url = url('app/payment');
            }
        } else if ($head == 'registration_fee') {
            $registration = Registration::find($transaction->transactionable_id);
            $url = url("app/student/registrations/{$registration->uuid}");
            $actionText = trans('global.go_to', ['attribute' => trans('student.registration.registration')]);

            if (empty($transaction->user_id)) {
                $url = url('app/payment');
            }
        }

        if ($transactionUuid != $transaction->uuid) {
            // dd($transactionUuid, $transaction->uuid);

            return view('messages.student.payment', [
                'message' => trans('finance.id_mismatch', ['attribute' => $referenceNumber]),
                'url' => $url,
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => $actionText,
            ]);
        }

        $status = Arr::get($response, 'auth_status');

        if ($status !== '0300') {
            return view('messages.student.payment', [
                'message' => trans('finance.transaction_failed', ['attribute' => $referenceNumber]),
                'url' => $url,
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => $actionText,
            ]);
        }

        $amount = Arr::get($response, 'amount');

        if ($amount != $transaction->amount->value) {
            return view('messages.student.payment', [
                'message' => trans('finance.amount_mismatch', ['attribute' => $referenceNumber]),
                'url' => $url,
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => $actionText,
            ]);
        }

        $refundInfo = Arr::get($response, 'refundInfo', []);

        if (count($refundInfo)) {
            return view('messages.student.payment', [
                'message' => trans('finance.amount_refunded', ['attribute' => $referenceNumber]),
                'url' => $url,
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => $actionText,
            ]);
        }

        if ($head == 'student_fee') {
            if ($transaction->user_id && empty(auth()->user())) {
                \Auth::loginUsingId($transaction->user_id);
                SysHelper::setTeam($transaction->period->team_id);
            }

            \DB::beginTransaction();

            (new PayOnlineFee)->studentFeePayment($student, $transaction);

            \DB::commit();

            $receiptUrl = route('students.transactions.export', ['student' => $student->uuid, 'transaction' => $transaction->uuid]);

            return view('messages.student.payment', [
                'message' => trans('finance.payment_succeed', ['amount' => $transaction->amount->formatted, 'attribute' => $referenceNumber]),
                'type' => 'success',
                'url' => $url,
                'studentUrl' => $studentUrl,
                'receiptUrl' => $receiptUrl,
                'actionText' => $actionText,
            ]);
        } elseif ($head == 'registration_fee') {
            if ($transaction->user_id && empty(auth()->user())) {
                \Auth::loginUsingId($transaction->user_id);
                SysHelper::setTeam($transaction->period->team_id);
            }

            \DB::beginTransaction();

            $registration = Registration::find($transaction->transactionable_id);

            (new PayOnlineFee)->registrationFeePayment($registration, $transaction);

            $transaction->payment_gateway = array_merge($transaction->payment_gateway, [
                'status' => 'updated',
                'transactionid' => Arr::get($response, 'transactionid'),
                'bankid' => Arr::get($response, 'bankid'),
                'payment_method_type' => Arr::get($response, 'payment_method_type'),
            ]);
            $transaction->processed_at = now()->toDateTimeString();
            $transaction->save();

            $registration->payment_status = PaymentStatus::PAID;
            $registration->save();

            \DB::commit();

            return view('messages.student.payment', [
                'message' => trans('finance.payment_succeed', ['amount' => $transaction->amount->formatted, 'attribute' => $referenceNumber]),
                'type' => 'success',
                'url' => $url,
                'studentUrl' => $studentUrl,
                'actionText' => $actionText,
            ]);
        }

        return view('messages.student.payment', [
            'message' => trans('general.errors.invalid_operation'),
            'url' => $url,
            'type' => 'error',
            'studentUrl' => $studentUrl,
            'actionText' => $actionText,
        ]);
    }

    public function cancel(Request $request)
    {
        return view('messages.student.payment', [
            'message' => trans('finance.payment_cancelled', ['attribute' => $request->orderid]),
            'url' => route('app'),
            'actionText' => trans('global.go_to', ['attribute' => trans('dashboard.dashboard')]),
        ]);
    }
}
