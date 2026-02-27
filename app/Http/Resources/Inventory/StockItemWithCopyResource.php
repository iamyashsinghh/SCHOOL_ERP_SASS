<?php

namespace App\Http\Resources\Inventory;

use App\Enums\Inventory\ItemTrackingType;
use Illuminate\Http\Resources\Json\JsonResource;

class StockItemWithCopyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->stock_item_copy_uuid ?? $this->stock_item_uuid,
            'name' => $this->name,
            'code' => $this->code,
            'code_number' => $this->code_number,
            'tracking_type' => ItemTrackingType::getDetail($this->tracking_type),
            'stock_item_copy_uuid' => $this->stock_item_copy_uuid,
        ];
    }
}
