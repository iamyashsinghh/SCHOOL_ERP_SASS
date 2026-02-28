<?php

namespace App\Actions\Student;

use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Finance\FeeConcessionRecord;

class CalculateFeeConcession
{
    public function execute(?FeeConcession $feeConcession = null, int $feeHeadId = 0, float $amount = 0): float
    {
        if (! $feeConcession) {
            return 0;
        }

        if ($amount <= 0) {
            return 0;
        }

        $feeConcessionRecord = $feeConcession->records->firstWhere('fee_head_id', $feeHeadId);

        if (! $feeConcessionRecord) {
            return 0;
        }

        $concessionAmount = (new CalculateConcession())->calculateAmount(
            $feeConcessionRecord->type,
            $feeConcessionRecord->value,
            $amount
        );

        $afterConcessionAmount = \Price::from($amount - $concessionAmount)->value;

        $enableSecondaryConcession = (bool) $feeConcession->getMeta('enable_secondary_concession', false);

        if (! $enableSecondaryConcession) {
            return $concessionAmount;
        }

        $secondaryConcessionType = $feeConcessionRecord->getMeta('secondary_type', 'percent');
        $secondaryConcessionValue = (float) $feeConcessionRecord->getMeta('secondary_value', 0);

        $secondaryConcessionAmount = (new CalculateConcession())->calculateAmount(
            $secondaryConcessionType,
            $secondaryConcessionValue,
            $afterConcessionAmount
        );

        return \Price::from($concessionAmount + $secondaryConcessionAmount)->value;

        // if ($feeConcessionRecord->type == 'percent') {
        //     if (config('config.finance.enable_round_off_fee_concession') && config('config.finance.concession_round_off_type', 'round_before_subtraction') == 'round_after_subtraction') {
        //         $feeAmount = round($amount - ($amount * ($feeConcessionRecord->value / 100)));

        //         return \Price::from($amount - $feeAmount)->value;
        //     }

        //     $concessionAmount = $amount * ($feeConcessionRecord->value / 100);
        //     if (config('config.finance.enable_round_off_fee_concession')) {
        //         $concessionAmount = round($concessionAmount);
        //     }

        //     return \Price::from($concessionAmount)->value;
        // }

        // $concessionAmount = $feeConcessionRecord->value;

        // if (config('config.finance.enable_round_off_fee_concession')) {
        //     $concessionAmount = round($concessionAmount);
        // }

        // return \Price::from($concessionAmount)->value;
    }
}
