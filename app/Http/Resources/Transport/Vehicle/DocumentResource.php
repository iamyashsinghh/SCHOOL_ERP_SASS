<?php

namespace App\Http\Resources\Transport\Vehicle;

use App\Http\Resources\MediaResource;
use App\Http\Resources\Transport\Vehicle\Config\DocumentTypeResource;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'vehicle' => VehicleResource::make($this->whenLoaded('documentable')),
            'type' => DocumentTypeResource::make($this->whenLoaded('type')),
            'title' => $this->title,
            'number' => $this->number,
            'description' => $this->description,
            'issue_date' => $this->issue_date,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_expired' => $this->is_expired,
            'calculated_expiry_in_days' => $this->calculated_expiry_in_days,
            'expiry_in_days' => $this->expiry_in_days,
            'show_expiry_date_alert' => $this->show_expiry_date_alert,
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
