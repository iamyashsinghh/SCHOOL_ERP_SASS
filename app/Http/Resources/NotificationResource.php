<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NotificationResource extends JsonResource
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
            'subject' => $this?->subject,
            'content' => $this?->content,
            'sender' => $this->getSender($request),
            'read_at' => $this->read_at,
            'is_read' => $this->read_at->value ? true : false,
            'type' => $this->getType($request),
            'link' => $this->getLink($request),
            'module_uuid' => Arr::get($this->data, 'uuid'),
            'sub_module_uuid' => Arr::get($this->data, 'module_uuid'),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function getSender($request): array
    {
        if ($this->sender_user_id) {
            return [
                'uuid' => $this->sender->uuid,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar,
            ];
        }

        return [
            'uuid' => (string) Str::uuid(),
            'name' => trans('notification.system'),
            'avatar' => config('config.assets.icon'),
        ];
    }

    private function getType($request): array
    {
        return [
            'name' => $this->type,
            'label' => trans('notification.type'),
            'value' => 'type',
            'icon' => 'fas fa-bell',
        ];
    }

    private function getLink($request): array
    {
        $isClickable = false;
        $route = [];

        if ($this->type == 'Reminder') {
            $isClickable = true;
            $route = [
                'name' => 'ReminderShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'StudentDiary') {
            $isClickable = true;
            $route = [
                'name' => 'ResourceDiary',
            ];
        } elseif ($this->type == 'Assignment') {
            $isClickable = true;
            $route = [
                'name' => 'ResourceAssignmentShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'LearningMaterial') {
            $isClickable = true;
            $route = [
                'name' => 'ResourceLearningMaterialShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Announcement') {
            $isClickable = true;
            $route = [
                'name' => 'CommunicationAnnouncementShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Event') {
            $isClickable = true;
            $route = [
                'name' => 'CalendarEventShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'JobApplication') {
            $isClickable = true;
            $route = [
                'name' => 'RecruitmentApplicationShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'EmployeeLeaveRequest') {
            $isClickable = true;
            $route = [
                'name' => 'EmployeeLeaveRequestShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Task') {
            $isClickable = true;
            $route = [
                'name' => 'TaskShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Ticket') {
            $isClickable = true;
            $route = [
                'name' => 'HelpdeskTicketShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Enquiry') {
            $isClickable = true;
            $route = [
                'name' => 'ReceptionEnquiryShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Complaint') {
            $isClickable = true;
            $route = [
                'name' => 'ReceptionComplaintShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        } elseif ($this->type == 'Registration') {
            $isClickable = true;
            $route = [
                'name' => 'StudentRegistrationShow',
                'params' => [
                    'uuid' => $this->getMeta('uuid'),
                ],
            ];
        }

        return [
            'is_clickable' => $isClickable,
            'route' => $route,
        ];
    }
}
