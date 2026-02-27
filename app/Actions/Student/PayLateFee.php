<?php

namespace App\Actions\Student;

use App\Enums\Finance\DefaultFeeHead;
use App\Models\Finance\Transaction;
use App\Models\Student\Fee;
use App\Models\Student\FeePayment;
use App\Models\Student\FeeRecord;

class PayLateFee
{
    public function execute(Fee $studentFee, Transaction $transaction, float $amount = 0): Fee
    {
        if ($amount <= 0) {
            return $studentFee;
        }

        $date = $transaction->date->value;

        $customLateFee = (bool) $studentFee->getMeta('custom_late_fee');

        if ($customLateFee) {
            $lateFeeAmount = $studentFee->getMeta('late_fee_amount') ?? 0;
        } else {
            $lateFeeAmount = $studentFee->calculateLateFeeAmount($date)->value;
        }

        if ($lateFeeAmount <= 0) {
            return $studentFee;
        }

        $lateFeeRecord = FeeRecord::firstOrCreate([
            'student_fee_id' => $studentFee->id,
            'default_fee_head' => DefaultFeeHead::LATE_FEE,
        ]);

        // fixed late fee sum issue while paying multiple times late fee
        $lateFeeRecord->amount = $lateFeeRecord->amount->value + $lateFeeAmount;
        $lateFeeRecord->paid = $lateFeeRecord->paid->value + $lateFeeAmount;
        $lateFeeRecord->save();

        $studentFee->total = $studentFee->total->value + $lateFeeAmount;

        if ($lateFeeAmount > 0) {
            FeePayment::forceCreate([
                'student_fee_id' => $studentFee->id,
                'default_fee_head' => DefaultFeeHead::LATE_FEE,
                'transaction_id' => $transaction->id,
                'amount' => $lateFeeAmount,
            ]);
        }

        return $studentFee;
    }
}
