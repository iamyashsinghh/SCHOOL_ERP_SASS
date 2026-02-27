<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Student\Fee;
use Illuminate\Http\Request;

class CalculationMismatchController extends Controller
{
    public function __invoke(Request $request)
    {
        $skip = (int) $request->query('skip', 0);
        $limit = (int) $request->query('limit', 500);

        $studentFees = Fee::query()
            ->select('student_fees.*', 'contacts.first_name as name', 'teams.name as team_name', 'periods.name as period_name', 'admissions.code_number as admission_code')
            ->with('records')
            ->join('students', 'students.id', '=', 'student_fees.student_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->join('periods', 'periods.id', '=', 'students.period_id')
            ->join('contacts', 'contacts.id', '=', 'students.contact_id')
            ->join('teams', 'teams.id', '=', 'contacts.team_id')
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'asc')
            ->get();

        $data = [];
        foreach ($studentFees as $fee) {
            if ($fee->additional_discount->value > 0) {
                continue;
            }

            if ($fee->additional_charge->value > 0) {
                continue;
            }

            $recordTotal = $fee->records->sum(function ($record) {
                return $record->amount->value - $record->concession->value;
            });
            $feeTotal = $fee->total->value - $fee->additional_charge->value + $fee->additional_discount->value;

            if ($recordTotal != $feeTotal) {
                if ($request->query('fix') == 'yes') {
                    $fee->total = $recordTotal;
                    $fee->save();
                } else {
                    $data[] = [
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

        return view('custom.calculation-mismatch', compact('data'));
    }
}
