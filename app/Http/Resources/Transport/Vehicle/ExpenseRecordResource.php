<?php

namespace App\Http\Resources\Transport\Vehicle;

use App\Http\Resources\MediaResource;
use App\Http\Resources\ReminderResource;
use App\Http\Resources\Transport\Vehicle\Config\ExpenseTypeResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseRecordResource extends JsonResource
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
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'type' => ExpenseTypeResource::make($this->whenLoaded('type')),
            'date' => $this->date,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price_per_unit' => $this->price_per_unit,
            'amount' => $this->amount,
            'log' => $this->log,
            'remarks' => $this->remarks,
            'reminder' => ReminderResource::make($this->whenLoaded('reminder')),
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
