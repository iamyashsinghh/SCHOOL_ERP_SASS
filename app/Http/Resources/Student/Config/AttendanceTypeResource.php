<?php

namespace App\Http\Resources\Student\Config;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AttendanceTypeResource extends JsonResource
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
            'sub_type' => $this->getSubType(),
            'code' => $this->getMeta('code'),
            'position' => $this->getMeta('position', 0),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function getSubType(): array
    {
        return [
            'value' => $this->getMeta('sub_type'),
            'label' => trans('student.attendance_type.sub_types.'.$this->getMeta('sub_type')),
        ];
    }
}
