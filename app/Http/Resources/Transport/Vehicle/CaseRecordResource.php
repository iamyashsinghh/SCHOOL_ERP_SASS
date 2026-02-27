<?php

namespace App\Http\Resources\Transport\Vehicle;

use App\Http\Resources\MediaResource;
use App\Http\Resources\OptionResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseRecordResource extends JsonResource
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
            'type' => OptionResource::make($this->whenLoaded('type')),
            'title' => $this->title,
            'date' => $this->date,
            'penalty' => $this->penalty,
            'location' => $this->location,
            'description' => $this->description,
            'action' => $this->action,
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
