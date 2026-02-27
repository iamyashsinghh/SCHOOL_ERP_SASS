<?php

namespace App\Http\Resources\Reception;

use App\Helpers\CalHelper;
use App\Http\Resources\MediaResource;
use App\Http\Resources\Student\Config\DocumentTypeResource;
use Illuminate\Http\Resources\Json\JsonResource;

class EnquiryDocumentResource extends JsonResource
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
            'type' => DocumentTypeResource::make($this->whenLoaded('type')),
            'title' => $this->title,
            'number' => $this->number,
            'description' => $this->description,
            'issue_date' => $this->issue_date,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'period' => CalHelper::getPeriod($this->start_date->value, $this->end_date->value),
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
