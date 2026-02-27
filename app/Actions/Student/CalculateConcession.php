<?php

namespace App\Actions\Student;

class CalculateConcession
{
    public function calculateAmount(string $type = 'percent', float $value = 0, float $amount = 0): float
    {
        if ($amount <= 0 || $value <= 0) {
            return 0;
        }

        if ($type == 'percent') {
            if (config('config.finance.enable_round_off_fee_concession') && config('config.finance.concession_round_off_type', 'round_before_subtraction') == 'round_after_subtraction') {
                $feeAmount = round($amount - ($amount * ($value / 100)));

                return \Price::from($amount - $feeAmount)->value;
            }

            $concessionAmount = $amount * ($value / 100);
            if (config('config.finance.enable_round_off_fee_concession')) {
                $concessionAmount = round($concessionAmount);
            }

            return \Price::from($concessionAmount)->value;
        }

        $concessionAmount = $value;

        if (config('config.finance.enable_round_off_fee_concession')) {
            $concessionAmount = round($concessionAmount);
        }

        return \Price::from($concessionAmount)->value;
    }
}