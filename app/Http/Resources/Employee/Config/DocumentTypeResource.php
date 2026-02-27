<?php

namespace App\Http\Resources\Employee\Config;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DocumentTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description,
            'description_summary' => Str::summary($this->description),
            'type' => $this->type,
            'has_number' => (bool) $this->getMeta('has_number'),
            'number_format' => $this->getMeta('number_format'),
            'has_expiry_date' => (bool) $this->getMeta('has_expiry_date'),
            'alert_days_before_expiry' => (int) $this->getMeta('alert_days_before_expiry'),
            'is_document_required' => (bool) $this->getMeta('is_document_required'),
            'position' => $this->getMeta('position', 0),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
