<?php

namespace App\Http\Resources\Helpdesk\Ticket;

use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Http\Resources\Employee\EmployeeSummaryResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TeamResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class TicketResource extends JsonResource
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
            'title' => $this->title,
            'category' => OptionResource::make($this->whenLoaded('category')),
            'priority' => OptionResource::make($this->whenLoaded('priority')),
            'list' => OptionResource::make($this->whenLoaded('list')),
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'team' => TeamResource::make($this->whenLoaded('team')),
            'employee' => $this->getEmployee($request, $this->user_id),
            'assignees' => $this->getAssignees($request),
            'messages' => $this->getMessages($request),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tag_summary' => $this->showTags(),
            'description' => $this->description,
            'status' => TicketStatus::getDetail($this->status),
            $this->mergeWhen($request->has_custom_fields, [
                'custom_fields' => $this->getCustomFieldsValues(),
            ]),
            'is_requester' => $this->is_requester,
            'is_reviewer' => $this->is_reviewer,
            'is_editable' => $this->isEditable(),
            'resolved_at' => $this->resolved_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function getEmployee($request, $userId)
    {
        $employees = $request->employees ?? collect([]);

        $employee = null;
        if ($employees->isNotEmpty()) {
            $employee = $employees->firstWhere('user_id', $userId);
        }

        return $employee ? EmployeeSummaryResource::make($employee) : [
            'uuid' => (string) Str::uuid(),
            'code_number' => 'ESM001',
            'name' => 'System Admin',
        ];
    }

    private function getAssignees($request)
    {
        if (! $this->relationLoaded('assignees')) {
            return [];
        }

        $employees = $request->employees ?? collect([]);

        return $this->assignees->map(function ($assignee) use ($employees) {
            $employee = $employees->firstWhere('user_id', $assignee->user_id);

            return EmployeeSummaryResource::make($employee);
        });
    }

    private function getMessages($request)
    {
        if (! $this->relationLoaded('messages')) {
            return [];
        }

        return $this->messages->map(function ($message) use ($request) {
            return [
                'uuid' => $message->uuid,
                'message' => $message->message,
                'status' => TicketStatus::getDetail($message->status),
                'employee' => $this->getEmployee($request, $message->user_id),
                'is_editable' => $message->is_editable,
                'media_token' => $message->getMeta('media_token'),
                'media' => MediaResource::collection($message->media),
                'created_at' => \Cal::dateTime($message->created_at),
                'updated_at' => \Cal::dateTime($message->updated_at),
            ];
        });
    }
}
