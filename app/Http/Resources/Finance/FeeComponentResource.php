<?php

namespace App\Http\Resources\Finance;

use App\Enums\Finance\TaxType;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeComponentResource extends JsonResource
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
            'name' => $this->name,
            'head' => FeeHeadResource::make($this->whenLoaded('head')),
            'tax' => TaxResource::make($this->whenLoaded('tax')),
            'tax_type' => TaxType::getDetail($this->getMeta('tax_type', 'inclusive')),
            'hsn_code' => $this->getMeta('hsn_code'),
            'description' => $this->description,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
