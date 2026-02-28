<?php

namespace App\Actions\Student;

use App\Models\Tenant\Student\Student;
use Illuminate\Validation\ValidationException;

class ValidateFeeTotal
{
    public function execute(Student $student)
    {
        foreach ($student->fees as $fee) {
            $total = 0;
            $paid = 0;
            foreach ($fee->records as $record) {
                $total += ($record->amount->value - $record->concession->value);
                $paid += $record->paid->value;
            }

            $total = \Price::from($total)?->value;
            $paid = \Price::from($paid)?->value;

            $feeTotal = $fee->total->value - $fee->additional_charge->value + $fee->additional_discount->value;
            $paidTotal = $fee->paid->value - $fee->additional_charge->value + $fee->additional_discount->value;

            $feeTotal = \Price::from($feeTotal)?->value;
            $paidTotal = \Price::from($paidTotal)?->value;

            if ($total != $feeTotal) {
                logger('Total : '.$total.' Fee Total: '.$feeTotal);
                throw ValidationException::withMessages(['message' => trans('student.fee.calculation_mismatch')]);
            }

            if ($paid != $paidTotal) {
                logger('Paid : '.$paid.' Paid Total: '.$paidTotal);
                throw ValidationException::withMessages(['message' => trans('student.fee.calculation_mismatch')]);
            }
        }
    }
}
