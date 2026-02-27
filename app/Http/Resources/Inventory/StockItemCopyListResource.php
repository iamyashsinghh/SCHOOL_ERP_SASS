<?php

namespace App\Http\Resources\Inventory;

use App\Enums\Inventory\HoldStatus;
use App\Http\Resources\Asset\Building\RoomResource;
use App\Http\Resources\OptionResource;
use Illuminate\Http\Resources\Json\JsonResource;

class StockItemCopyListResource extends JsonResource
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
            'uuid' => $this->uuid,
            'code_number' => $this->code_number,
            'item' => StockItemResource::make($this->whenLoaded('item')),
            'condition' => OptionResource::make($this->whenLoaded('condition')),
            'place' => RoomResource::make($this->whenLoaded('place')),
            'category' => $this->category,
            'inventory' => $this->inventory,
            'vendor' => $this->vendor,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'price' => $this->price,
            'hold_status' => empty($this->hold_status?->value) ? [
                'label' => trans('inventory.stock_item.copy.statuses.stock'),
                'value' => 'stock',
            ] : HoldStatus::getDetail($this->hold_status),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
