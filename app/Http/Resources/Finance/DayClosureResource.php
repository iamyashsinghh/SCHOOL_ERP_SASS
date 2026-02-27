<?php

namespace App\Http\Resources\Finance;

use App\Http\Resources\UserSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class DayClosureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $isAmountMismatch = (bool) $this->getMeta('is_amount_mismatch', false);

        return [
            'uuid' => $this->uuid,
            'date' => $this->date,
            'denominations' => collect($this->denominations)->filter(function ($item) {
                return (int) Arr::get($item, 'count', 0) > 0;
            })->map(function ($item) {
                return [
                    'name' => Arr::get($item, 'name'),
                    'count' => (int) Arr::get($item, 'count', 0),
                    'value' => Arr::get($item, 'name') * (int) Arr::get($item, 'count', 0),
                    'amount' => \Price::from(Arr::get($item, 'name') * (int) Arr::get($item, 'count', 0)),
                    'label' => \Price::from(Arr::get($item, 'name'))->formatted.' x '.Arr::get($item, 'count', 0).' = '.\Price::from(Arr::get($item, 'name') * (int) Arr::get($item, 'count', 0))->formatted,
                ];
            })->toArray(),
            'total' => $this->total,
            'is_amount_mismatch' => $isAmountMismatch,
            'user_collected_amount' => \Price::from($this->getMeta('user_collected_amount', 0)),
            $this->mergeWhen($isAmountMismatch, [
                'difference' => \Price::from(abs($this->total->value - $this->getMeta('user_collected_amount', 0))),
                'difference_sign' => $this->total->value < $this->getMeta('user_collected_amount', 0) ? '-' : '+',
                'reason' => $this->getMeta('reason'),
            ]),
            'type' => $this->getMeta('type', 'manual'),
            'is_editable' => $this->is_editable,
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'remarks' => $this->remarks,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
