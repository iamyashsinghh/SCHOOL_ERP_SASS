<?php

namespace App\Http\Resources\Helpdesk\Ticket;

use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Http\Resources\Employee\EmployeeSummaryResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\TeamResource;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $employees = $request->employees ?? collect([]);

        $employee = null;
        if ($employees->isNotEmpty()) {
            $employee = $employees->firstWhere('user_id', $this->user_id);
        }

        return [
            'uuid' => $this->uuid,
            'code_number' => $this->code_number,
            'title' => $this->title,
            'category' => OptionResource::make($this->whenLoaded('category')),
            'priority' => OptionResource::make($this->whenLoaded('priority')),
            'list' => OptionResource::make($this->whenLoaded('list')),
            'status' => TicketStatus::getDetail($this->status),
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'team' => TeamResource::make($this->whenLoaded('team')),
            'employee' => EmployeeSummaryResource::make($employee),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
