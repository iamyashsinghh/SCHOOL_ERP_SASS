<?php

namespace App\Enums\Inventory;

use App\Concerns\HasEnum;

enum ItemType: string
{
    use HasEnum;

    case CONSUMABLE = 'consumable';
    case NON_CONSUMABLE = 'non_consumable';

    public static function translation(): string
    {
        return 'inventory.stock_item.types.';
    }
}
