<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student\Fee;
use Illuminate\Http\Request;

class FixAdditionalFeeMismatchController extends Controller
{
    public function __invoke(Request $request, $uuid)
    {
        $fee = Fee::query()
            ->with('records')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $recordTotal = $fee->records->sum(function ($record) {
            return $record->amount->value - $record->concession->value;
        });

        $feeTotal = $fee->total->value - $fee->additional_charge->value + $fee->additional_discount->value;

        // if ($request->dd) {
        //     $data = [
        //         'Fee Total' => $fee->total->value,
        //         'Fee Total With Additional' => $feeTotal,
        //         'Record Total' => $recordTotal,
        //         'Fee Paid' => $fee->paid->value,
        //     ];

        //     return $data;
        // }

        if ($recordTotal != $feeTotal && $feeTotal != $fee->paid->value) {
            $fee->total = $fee->paid->value;
            $date = now()->format('Y_m_d');
            $fee->setMeta([
                'previous_total_on_'.$date => $feeTotal,
            ]);
            $fee->save();

            return 'Fee total updated';
        } else {
            $data = [
                'Fee Total' => $fee->total->value,
                'Fee Total With Additional' => $feeTotal,
                'Record Total' => $recordTotal,
                'Fee Paid' => $fee->paid->value,
            ];

            return $data;
        }
    }
}
