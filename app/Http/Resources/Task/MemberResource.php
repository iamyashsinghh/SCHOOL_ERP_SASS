<?php

namespace App\Http\Resources\Task;

use App\Http\Resources\Employee\EmployeeSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
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
            'employee' => EmployeeSummaryResource::make($this->whenLoaded('employee')),
            'is_owner' => $this->is_owner ? true : false,
            'manage_member' => (bool) $this->getMeta('permission.manage_member'),
            'manage_checklist' => (bool) $this->getMeta('permission.manage_checklist'),
            'manage_media' => (bool) $this->getMeta('permission.manage_media'),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
