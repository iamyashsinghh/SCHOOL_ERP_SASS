<?php

namespace App\Http\Resources\Finance;

use App\Http\Resources\Academic\PeriodResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class FeeConcessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $transportConcessionType = Arr::get($this->transport, 'type', 'percent');

        if ($transportConcessionType == 'amount') {
            $transportConcessionValue = \Price::from(Arr::get($this->transport, 'value', 0));
        } else {
            $transportConcessionValue = \Percent::from(Arr::get($this->transport, 'value', 0));
        }

        $transportSecondaryType = Arr::get($this->transport, 'secondary_type', 'percent');
        $transportSecondaryValue = null;
        if ($transportSecondaryType) {
            if ($transportSecondaryType == 'amount') {
                $transportSecondaryValue = \Price::from(Arr::get($this->transport, 'secondary_value', 0));
            } elseif ($transportSecondaryType == 'percent') {
                $transportSecondaryValue = \Percent::from(Arr::get($this->transport, 'secondary_value', 0));
            }
        }

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->getMeta('code'),
            'enable_secondary_concession' => (bool) $this->getMeta('enable_secondary_concession', false),
            'period' => PeriodResource::make($this->whenLoaded('period')),
            'records' => FeeConcessionRecordResource::collection($this->whenLoaded('records')),
            'transport_type' => $transportConcessionType,
            'transport_value' => $transportConcessionValue,
            'transport_secondary_type' => $transportSecondaryType,
            'transport_secondary_value' => $transportSecondaryValue,
            'description' => $this->description,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
