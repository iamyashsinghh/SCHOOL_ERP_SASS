<?php

namespace App\Http\Resources\Task;

use App\Enums\Day;
use App\Enums\RepeatFrequency;
use App\Helpers\SysHelper;
use App\Http\Resources\Employee\EmployeeSummaryResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TeamResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $memberCount = 0;
        if ($this->relationLoaded('memberLists')) {
            $memberCount = $this->memberLists()->count();
            if ($memberCount > 0) {
                $memberCount = $memberCount - 1;
            }
        }

        return [
            'uuid' => $this->uuid,
            'code_number' => $this->code_number,
            'title' => $this->title,
            $this->mergeWhen($request->detail, [
                'description' => $this->description,
            ]
            ),
            'media_count' => $this->when($this->relationLoaded('media'), $this->media->count()),
            'checklist_count' => $this->when($this->relationLoaded('checklists'), $this->checklists()->count()),
            'member_count' => $this->when($this->relationLoaded('memberLists'), $memberCount),
            'is_owner' => $this->is_owner,
            'is_member' => $this->is_member,
            'is_favorite' => $this->is_favorite,
            'owner' => EmployeeSummaryResource::make($this->owner),
            'members' => MemberResource::collection($this->whenLoaded('members')),
            'priority' => OptionResource::make($this->whenLoaded('priority')),
            'category' => OptionResource::make($this->whenLoaded('category')),
            'list' => OptionResource::make($this->whenLoaded('list')),
            'start_date' => $this->start_date,
            'due_date' => $this->due_date,
            'due_time' => $this->due_date_time,
            'due' => $this->due,
            'is_due_today' => $this->due_date->value == today()->toDateString() ? true : false,
            'is_overdue' => $this->is_overdue,
            $this->mergeWhen($this->is_overdue, [
                'overdue_days' => $this->overdue_days,
                'overdue_days_display' => trans('task.props.overdue_by', ['day' => $this->overdue_days]),
            ]),
            'has_progress' => $this->getMeta('has_progress') ? true : false,
            $this->mergeWhen($this->getMeta('has_progress'), [
                'progress' => $this->progress,
                'progress_display' => SysHelper::formatPercentage($this->progress),
                'progress_color' => SysHelper::getPercentageColor($this->progress),
            ]),
            'is_cancelled' => $this->cancelled_at->value ? true : false,
            'cancelled_at' => $this->cancelled_at,
            'is_archived' => $this->archived_at->value ? true : false,
            'archived_at' => $this->archived_at,
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tag_summary' => $this->showTags(),
            'permission' => [
                'is_editable' => $this->isEditable(),
                'is_deletable' => $this->isDeletable(),
                'is_actionable' => $this->isActionable(),
                'manage_member' => $this->canManage('member'),
                'manage_checklist' => $this->canManage('checklist'),
                'manage_task_list' => $this->canManage('task_list'),
                'manage_media' => $this->canManage('media'),
                'manage_completion' => $this->canManage('completion'),
                'mark_as_complete' => $this->canMarkAsComplete(),
                'mark_as_incomplete' => $this->canMarkAsIncomplete(),
                'mark_as_cancel' => $this->canMarkAsCancel(),
                'mark_as_active' => $this->canMarkAsActive(),
                'move_to_archive' => $this->canMoveToArchive(),
                'move_from_archive' => $this->canMoveFromArchive(),
                'toggle_favorite' => $this->canToggleFavorite(),
            ],
            $this->merge($this->getRepeatation()),
            'repeated_task_uuid' => $this->getMeta('repeated_task_uuid'),
            $this->mergeWhen($request->has_custom_fields, [
                'custom_fields' => $this->getCustomFieldsValues(),
            ]),
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'team' => TeamResource::make($this->whenLoaded('team')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function getRepeatation(): array
    {
        $frequency = Arr::get($this->repeatation, 'frequency');
        $shouldRepeat = (bool) $this->should_repeat;

        $repeatation = $shouldRepeat ? [
            'start_date' => \Cal::date(Arr::get($this->repeatation, 'start_date')),
            'end_date' => \Cal::date(Arr::get($this->repeatation, 'end_date')),
            'next_repeat_date' => \Cal::date(Arr::get($this->repeatation, 'next_repeat_date')),
            'frequency' => RepeatFrequency::getDetail($frequency),
            'days' => $frequency == RepeatFrequency::DAY_WISE->value ? Arr::get($this->repeatation, 'days', []) : [],
            'days_display' => $frequency == RepeatFrequency::DAY_WISE->value ? Day::getLabels(Arr::get($this->repeatation, 'days', [])) : [],
            'dates' => $frequency == RepeatFrequency::DATE_WISE->value ? Arr::get($this->repeatation, 'dates', []) : [],
            'dates_display' => $frequency == RepeatFrequency::DATE_WISE->value ? implode(', ', Arr::get($this->repeatation, 'dates', [])) : [],
            'day_wise_count' => (int) Arr::get($this->repeatation, 'day_wise_count', 0),
        ] : [];

        return [
            'should_repeat' => $shouldRepeat,
            'repeatation' => $repeatation,
        ];
    }
}
