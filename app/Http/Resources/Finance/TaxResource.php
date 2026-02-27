<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TaxResource extends JsonResource
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
            'rate' => $this->rate,
            'code_with_rate' => $this->code_with_rate,
            'components' => collect($this->components)->map(function ($component) {
                return [
                    'name' => Arr::get($component, 'name'),
                    'code' => Arr::get($component, 'code'),
                    'rate' => \Percent::From(Arr::get($component, 'rate')),
                ];
            }),
            'has_components' => count($this->components) > 0 ? true : false,
            'description' => $this->description,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
