<?php

namespace App\Actions\Student;

use App\Models\Finance\FeeConcession;
use Illuminate\Support\Arr;

class GetTransportConcessionFeeAmount
{
    public function execute(?FeeConcession $feeConcession = null, float $transportFeeAmount = 0): float
    {
        if (! $feeConcession) {
            return 0;
        }

        if ($transportFeeAmount <= 0) {
            return 0;
        }

        $concessionType = Arr::get($feeConcession->transport, 'type', 'percent');
        $concessionValue = Arr::get($feeConcession->transport, 'value', 0);

        $concessionAmount = (new CalculateConcession())->calculateAmount(
            $concessionType,
            $concessionValue,
            $transportFeeAmount
        );

        $afterConcessionAmount = \Price::from($transportFeeAmount - $concessionAmount)->value;

        $enableSecondaryConcession = (bool) $feeConcession->getMeta('enable_secondary_concession', false);

        if (! $enableSecondaryConcession) {
            return $concessionAmount;
        }

        $secondaryConcessionType = Arr::get($feeConcession->transport, 'secondary_type', 'percent');
        $secondaryConcessionValue = (float) Arr::get($feeConcession->transport, 'secondary_value', 0);

        $secondaryConcessionAmount = (new CalculateConcession())->calculateAmount(
            $secondaryConcessionType,
            $secondaryConcessionValue,
            $afterConcessionAmount
        );

        return \Price::from($concessionAmount + $secondaryConcessionAmount)->value;

        // if ($concessionType == 'amount') {
        //     return \Price::from($concessionValue)->value;
        // }

        // return \Price::from($transportFeeAmount * ($concessionValue / 100))->value;
    }
}
