<?php

namespace App\Http\Resources\Finance;

use App\Enums\Finance\DefaultCustomFeeType;
use App\Enums\Finance\TaxType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class FeeHeadResource extends JsonResource
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
            'code' => $this->code,
            'slug' => $this->slug,
            'slug_camel' => Str::camel($this->slug),
            'shortcode' => $this->shortcode,
            'group' => FeeGroupResource::make($this->whenLoaded('group')),
            'type' => DefaultCustomFeeType::getDetail($this->type),
            'tax' => TaxResource::make($this->whenLoaded('tax')),
            'tax_type' => TaxType::getDetail($this->getMeta('tax_type', 'inclusive')),
            'components' => FeeComponentResource::collection($this->whenLoaded('components')),
            'hsn_code' => $this->getMeta('hsn_code'),
            'description' => $this->description,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
