<?php

namespace App\Enums\Finance;

use App\Concerns\HasEnum;

enum TaxType: string
{
    use HasEnum;

    case INCLUSIVE = 'inclusive';
    case EXCLUSIVE = 'exclusive';

    public static function translation(): string
    {
        return 'finance.tax.types.';
    }
}
