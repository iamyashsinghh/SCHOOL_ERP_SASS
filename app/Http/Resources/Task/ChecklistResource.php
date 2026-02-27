<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date,
            'due_time' => $this->due_date_time,
            'due' => $this->due,
            'is_overdue' => $this->is_overdue,
            'overdue_days' => $this->overdue_days,
            'overdue_days_display' => trans('task.props.overdue_by', ['day' => $this->overdue_days]),
            'completed_at' => $this->completed_at,
            'is_completed' => $this->completed_at->value ? true : false,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
