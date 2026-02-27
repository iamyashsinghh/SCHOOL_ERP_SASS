<?php

namespace App\Http\Resources\Employee;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RecordResource extends JsonResource
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
            'department' => ['name' => $this->department_name, 'uuid' => $this->department_uuid],
            'designation' => ['name' => $this->designation_name, 'uuid' => $this->designation_uuid],
            'employment_status' => ['name' => $this->employment_status_name, 'uuid' => $this->employment_status_uuid],
            'team' => [
                'name' => $this->team_name,
                'uuid' => $this->team_uuid,
                'id' => $this->team_id,
            ],
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'period' => $this->period,
            'duration' => $this->duration,
            'remarks' => $this->remarks,
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'is_ended' => $this->is_ended,
            'current_team' => $this->team_id == auth()->user()?->current_team_id,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
