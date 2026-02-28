<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\HoldStatus;
use App\Enums\OptionType;
use App\Http\Resources\Asset\Building\RoomResource;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Asset\Building\Room;
use App\Models\Tenant\Option;

class StockItemCopyService
{
    public function preRequisite()
    {
        $conditions = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::STOCK_ITEM_CONDITION)
            ->get());

        $places = RoomResource::collection(Room::query()
            ->withFloorAndBlock()
            ->get());

        $statuses = [
            [
                'label' => trans('inventory.stock_item.statuses.hold'),
                'value' => 'hold',
            ],
            [
                'label' => trans('inventory.stock_item.statuses.stock'),
                'value' => 'stock',
            ],
        ];

        $holdStatuses = HoldStatus::getOptions();

        return compact('conditions', 'places', 'statuses', 'holdStatuses');
    }
}
