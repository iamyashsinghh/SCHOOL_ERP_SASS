<?php

namespace App\Actions\PaymentGateway;

use App\Actions\Student\PayOnlineFee;
use App\Models\Finance\Transaction;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class UpdateHubtelPayment
{
    public function execute(Request $request, Student $student, Transaction $transaction)
    {
        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');

        $url = 'https://api-txnstatus.hubtel.com/transactions/'.config('config.finance.hubtel_merchant').'/status?clientReference='.$referenceNumber;

        $basicAuth = base64_encode(config('config.finance.hubtel_client').':'.config('config.finance.hubtel_secret'));

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$basicAuth,
        ])
            ->get($url);

        $data = $response->json();

        if (Arr::get($data, 'responseCode') != '0000' || Arr::get($data, 'message') != 'Successful') {
            $this->failTransaction($transaction, 'Invalid response from Hubtel');

            return;
        }

        if (Arr::get($data, 'data.status') != 'Paid') {
            $this->failTransaction($transaction, Arr::get($data, 'data.status', 'Payment failed'));

            return;
        }

        if (Arr::get($data, 'data.transactionId') != Arr::get($transaction->payment_gateway, 'payment_intent_id')) {
            $this->failTransaction($transaction, 'Transaction ID mismatch');

            return;
        }

        // if (Arr::get($data, 'Data.Amount') != $transaction->amount->value) {
        //     return [];
        // }

        \DB::beginTransaction();

        if ($transaction->head == 'student_fee') {
            (new PayOnlineFee)->studentFeePayment($student, $transaction);
        }

        \DB::commit();
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
