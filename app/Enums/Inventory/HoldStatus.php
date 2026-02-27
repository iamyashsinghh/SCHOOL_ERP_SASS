<?php

namespace App\Enums\Inventory;

use App\Concerns\HasEnum;

enum HoldStatus: string
{
    use HasEnum;

    case DAMAGED = 'damaged';
    case LOST = 'lost';

    public static function translation(): string
    {
        return 'inventory.stock_item.hold_statuses.';
    }
}
