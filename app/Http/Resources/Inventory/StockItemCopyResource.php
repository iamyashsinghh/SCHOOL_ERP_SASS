<?php

namespace App\Http\Resources\Inventory;

use App\Http\Resources\OptionResource;
use Illuminate\Http\Resources\Json\JsonResource;

class StockItemCopyResource extends JsonResource
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
            'price' => $this->price,
            'vendor' => $this->vendor,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
