<?php

namespace App\Services\PaymentGateway;

use App\Actions\Student\PayOnlineFee;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Transaction;
use App\Models\Student\Registration;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class HubtelService
{
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

        $url = 'https://api-txnstatus.hubtel.com/transactions/'.config('config.finance.hubtel_merchant').'/status?clientReference='.$request->query('reference_number');

        $basicAuth = base64_encode(config('config.finance.hubtel_client').':'.config('config.finance.hubtel_secret'));

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$basicAuth,
        ])
            ->get($url);

        dd($response->body());
    }

    public function callback(Request $request)
    {
        $data = $request->all();

        $checkoutId = Arr::get($data, 'Data.CheckoutId');

        $transaction = Transaction::query()
            ->where('payment_gateway->payment_intent_id', $checkoutId)
            ->first();

        if (! $transaction) {
            return [];
        }

        if ($transaction->processed_at->value) {
            return [];
        }

        if (Arr::get($data, 'ResponseCode') != '0000' || Arr::get($data, 'Status') != 'Success') {
            $this->failTransaction($transaction, Arr::get($data, 'Data.Status', 'Payment failed'));

            return [];
        }

        // if (Arr::get($data, 'Data.Amount') != $transaction->amount->value) {
        //     return [];
        // }

        \DB::beginTransaction();

        if ($transaction->head == 'student_fee') {
            $student = Student::query()
                ->findOrFail($transaction->transactionable_id);

            (new PayOnlineFee)->studentFeePayment($student, $transaction);

        } elseif ($transaction->head == 'registration_fee') {
            $registration = Registration::query()
                ->findOrFail($transaction->transactionable_id);

            $paymentStatus = PaymentStatus::PAID;

            if ($transaction->amount->value < $registration->fee->value) {
                $paymentStatus = PaymentStatus::PARTIALLY_PAID;
            }

            $registration->payment_status = $paymentStatus;
            $registration->save();
        }

        \DB::commit();
    }

    public function getResponse(Request $request)
    {
        $checkoutId = $request->query('checkoutid');
        $referenceNumber = $request->query('reference_number');

        $transaction = Transaction::query()
            ->with('period')
            ->when($referenceNumber, function ($query) use ($referenceNumber) {
                $query->where('payment_gateway->reference_number', $referenceNumber);
            })->when($checkoutId, function ($query) use ($checkoutId) {
                $query->where('payment_gateway->payment_intent_id', $checkoutId);
            })
            ->first();

        if (! $transaction) {
            abort(404);
        }

        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');

        if ($transaction->head == 'student_fee') {
            $student = Student::find($transaction->transactionable_id);
            $studentUrl = url('/app/students/'.$student?->uuid.'/fee');
            $receiptUrl = route('students.transactions.export', ['student' => $student->uuid, 'transaction' => $transaction->uuid]);
            $url = url("app/students/{$student->uuid}/fee");
            $actionText = trans('global.go_to', ['attribute' => trans('student.fee.fee')]);
        } else {
            $url = route('app');
            $studentUrl = route('app');
            $receiptUrl = route('app');
            $actionText = trans('global.go_to', ['attribute' => trans('dashboard.dashboard')]);
        }

        if ($transaction->processed_at->value) {
            return view('messages.student.payment', [
                'message' => trans('finance.payment_succeed', ['amount' => $transaction->amount->formatted, 'attribute' => $referenceNumber]),
                'type' => 'success',
                'url' => $url,
                'studentUrl' => $studentUrl,
                'receiptUrl' => $receiptUrl,
                'actionText' => $actionText,
            ]);
        } else {
            return view('messages.student.payment', [
                'message' => trans('finance.transaction_failed', ['attribute' => $referenceNumber]),
                'url' => $url,
                'type' => 'error',
                'studentUrl' => $studentUrl,
                'actionText' => $actionText,
            ]);
        }
    }

    private function failTransaction(Transaction $transaction, $error)
    {
        $failedLogs = $transaction->failed_logs;
        $failedLogs[] = [
            'name' => 'hubtel',
            'error' => $error,
            'failed_at' => now()->toDateTimeString(),
        ];
        $transaction->failed_logs = $failedLogs;
        $transaction->payment_gateway = array_merge($transaction->payment_gateway, [
            'status' => 'updated',
            'code' => $error,
        ]);

        $transaction->save();
    }
}
