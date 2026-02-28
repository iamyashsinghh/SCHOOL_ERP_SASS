<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Finance\TransactionRecord;
use App\Models\Tenant\Student\Fee;
use Illuminate\Http\Request;

class AdditionalFeeMismatchController extends Controller
{
    public function __invoke(Request $request)
    {
        $studentFees = Fee::query()
            ->select('student_fees.*', 'contacts.first_name as name', 'teams.name as team_name', 'periods.name as period_name', 'admissions.code_number as admission_code')
            ->with('records')
            ->join('students', 'students.id', '=', 'student_fees.student_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->join('periods', 'periods.id', '=', 'students.period_id')
            ->join('contacts', 'contacts.id', '=', 'students.contact_id')
            ->join('teams', 'teams.id', '=', 'contacts.team_id')
            ->where(function ($q) {
                $q->where('additional_charge', '>', 0)
                    ->orWhere('additional_discount', '>', 0);
            })
            ->get();

        $notPaidCount = 0;
        $paidCount = 0;
        $data = [];
        $paid = [];
        $notPaid = [];
        foreach ($studentFees as $fee) {
            // $data[] = [
            //     'record_total' => $fee->records->sum('amount.value'),
            //     'installment_total' => $fee->total->value - $fee->additional_charge->value + $fee->additional_discount->value,
            // ];

            $recordTotal = $fee->records->sum(function ($record) {
                return $record->amount->value - $record->concession->value;
            });
            $feeTotal = $fee->total->value - $fee->additional_charge->value + $fee->additional_discount->value;

            if ($recordTotal != $feeTotal) {
                $transactionRecords = TransactionRecord::query()
                    ->where('model_type', 'StudentFee')
                    ->where('model_id', $fee->id)
                    ->count();

                if ($transactionRecords == 1) {
                    $notPaid[] = [
                        'uuid' => $fee->uuid,
                        'name' => $fee->name,
                        'team_name' => $fee->team_name,
                        'period_name' => $fee->period_name,
                        'admission_code' => $fee->admission_code,
                        'record_total' => $recordTotal,
                        'fee_total' => $feeTotal,
                        'difference' => $feeTotal - $recordTotal,
                        'additional_charge' => $fee->additional_charge->value,
                        'additional_discount' => $fee->additional_discount->value,
                    ];
                } else {
                    $paid[] = [
                        'uuid' => $fee->uuid,
                        'name' => $fee->name,
                        'team_name' => $fee->team_name,
                        'period_name' => $fee->period_name,
                        'admission_code' => $fee->admission_code,
                        'record_total' => $recordTotal,
                        'fee_total' => $feeTotal,
                        'difference' => $feeTotal - $recordTotal,
                        'additional_charge' => $fee->additional_charge->value,
                        'additional_discount' => $fee->additional_discount->value,
                    ];
                }
            }
        }

        return view('custom.additional-fee-mismatch', compact('notPaid', 'paid'));
    }
}
