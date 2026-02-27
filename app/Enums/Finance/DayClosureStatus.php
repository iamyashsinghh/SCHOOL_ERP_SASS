<?php

namespace App\Enums\Finance;

use App\Concerns\HasEnum;
use App\Contracts\HasColor;

enum DayClosureStatus: string implements HasColor
{
    use HasEnum;

    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';

    public static function translation(): string
    {
        return 'finance.day_closure.statuses.';
    }

    public function color(): string
    {
        return match ($this) {
            self::SUBMITTED => 'info',
            self::APPROVED => 'success',
        };
    }
}
