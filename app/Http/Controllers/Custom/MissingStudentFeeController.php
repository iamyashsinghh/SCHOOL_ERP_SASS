<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Finance\Transaction;
use App\Models\Finance\TransactionRecord;
use Illuminate\Http\Request;

class MissingStudentFeeController extends Controller
{
    public function __invoke(Request $request)
    {
        // One student fee was logged in transaction record but student fee was deleted that too for online payment. So creating this route to find those student fees.

        $transactions = Transaction::query()
            ->select('id', 'code_number')
            ->whereNotNull('code_number')
            ->get()
            ->pluck('id')
            ->all();

        $transactionRecords = TransactionRecord::query()
            ->select('model_id')
            ->whereIn('transaction_id', $transactions)
            ->where('model_type', 'StudentFee')
            ->get()
            ->pluck('model_id')
            ->all();

        $missing = collect($transactionRecords)->diff(
            \DB::table('student_fees')->pluck('id')
        );

        $data = [];
        foreach ($missing as $id) {
            $transactionRecord = TransactionRecord::query()
                ->where('model_id', $id)
                ->where('model_type', 'StudentFee')
                ->first();

            $data[] = [
                'transaction_id' => $transactionRecord->transaction_id,
                'is_online' => $transactionRecord->transaction->is_online ? true : false,
                'student_id' => $transactionRecord->transaction->transactionable_id,
            ];
        }

        return $data;
    }
}
