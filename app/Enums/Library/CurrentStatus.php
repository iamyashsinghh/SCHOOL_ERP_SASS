<?php

namespace App\Enums\Library;

use App\Concerns\HasEnum;

enum CurrentStatus: string
{
    use HasEnum;

    case AVAILABLE = 'available';
    case ISSUED = 'issued';
    case RETURNED = 'returned';
    case PARTIALLY_RETURNED = 'partially_returned';
    case HOLD = 'hold';

    public static function translation(): string
    {
        return 'library.transaction.statuses.';
    }
}
