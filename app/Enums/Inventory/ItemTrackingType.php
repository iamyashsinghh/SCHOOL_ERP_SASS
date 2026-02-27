<?php

namespace App\Enums\Inventory;

use App\Concerns\HasEnum;

enum ItemTrackingType: string
{
    use HasEnum;

    case UNIQUE = 'unique';
    case BULK = 'bulk';

    public static function translation(): string
    {
        return 'inventory.stock_item.tracking_types.';
    }
}
