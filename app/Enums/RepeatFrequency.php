<?php

namespace App\Enums;

use App\Concerns\HasEnum;

enum RepeatFrequency: string
{
    use HasEnum;

    case DAY_WISE = 'day_wise';
    case DAY_WISE_COUNT = 'day_wise_count';
    case DATE_WISE = 'date_wise';
    case WEEKLY = 'weekly';
    case FORTNIGHTLY = 'fortnightly';
    case MONTHLY = 'monthly';
    case BI_MONTHLY = 'bi_monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half_yearly';
    case YEARLY = 'yearly';

    public static function translation(): string
    {
        return 'list.repeat_frequencies.';
    }
}
