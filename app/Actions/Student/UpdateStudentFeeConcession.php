<?php

namespace App\Actions\Student;

use App\Enums\Finance\DefaultFeeHead;
use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Student\Fee;

class UpdateStudentFeeConcession
{
    public function execute(FeeConcession $feeConcession): void
    {
        if (! auth()->user()->is_default) {
            return;
        }

        $feeConcession->loadMissing('records');

        $studentFees = Fee::query()
            ->with('records')
            ->whereFeeConcessionId($feeConcession->id)
            ->where('paid', 0)
            ->get();

        foreach ($studentFees as $studentFee) {

            foreach ($studentFee->records->filter(function ($record) {
                return ! empty($record->fee_head_id);
            }) as $feeRecord) {
                $concessionAmount = (new CalculateFeeConcession)->execute(
                    feeConcession: $feeConcession,
                    feeHeadId: $feeRecord->fee_head_id,
                    amount: $feeRecord->amount->value
                );

                $feeRecord->concession = $concessionAmount;
                $feeRecord->save();
            }

            foreach ($studentFee->records->filter(function ($record) {
                return $record->default_fee_head === DefaultFeeHead::TRANSPORT_FEE;
            }) as $feeRecord) {
                $transportFeeConcessionAmount = (new GetTransportConcessionFeeAmount)->execute(
                    feeConcession: $feeConcession,
                    transportFeeAmount: $feeRecord->amount->value
                );

                $feeRecord->concession = $transportFeeConcessionAmount;
                $feeRecord->save();
            }

            $studentFee->total = $studentFee->getInstallmentTotal()->value;
            $studentFee->save();
        }

    }
}
