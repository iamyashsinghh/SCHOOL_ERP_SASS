<?php

namespace App\Services\Task;

use App\Actions\CreateTag;
use App\Actions\GetNextRepeatDate;
use App\Enums\Day;
use App\Enums\Month;
use App\Enums\OptionType;
use App\Enums\RepeatFrequency;
use App\Helpers\CalHelper;
use App\Jobs\Notifications\Task\SendTaskCompletedNotification;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Media;
use App\Models\Tenant\Option;
use App\Models\Tenant\Task\Member;
use App\Models\Tenant\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskActionService
{
    public function updateTags(Request $request, Task $task)
    {
        $request->validate([
            'tags' => 'array',
            'tags.*' => 'required|string|distinct',
        ]);

        $tags = (new CreateTag)->execute($request->input('tags', []));

        $task->tags()->sync($tags);
    }

    public function toggleFavorite(Task $task)
    {
        $employee = Employee::auth()->first();

        $member = Member::whereTaskId($task->id)->whereEmployeeId($employee?->id)->first();

        if (! $member) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $member->is_favorite = ! $member->is_favorite;
        $member->save();
    }

    public function updateStatus(Request $request, Task $task)
    {
        if ($request->status == 'complete') {
            $this->markAsComplete($task);
        } elseif ($request->status == 'incomplete') {
            $this->markAsIncomplete($task);
        } elseif ($request->status == 'cancel') {
            $this->markAsCancel($task);
        } elseif ($request->status == 'active') {
            $this->markAsActive($task);
        } elseif ($request->status == 'archive') {
            $this->moveToArchive($task);
        } elseif ($request->status == 'unarchive') {
            $this->moveFromArchive($task);
        }
    }

    private function markAsComplete(Task $task)
    {
        if (! $task->canMarkAsComplete()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $task->completed_at = now()->toDateTimeString();
        $task->save();

        SendTaskCompletedNotification::dispatch([
            'task_id' => $task->id,
            'team_id' => auth()->user()->current_team_id,
        ]);
    }

    private function markAsIncomplete(Task $task)
    {
        if (! $task->canMarkAsIncomplete()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $task->completed_at = null;
        $task->save();
    }

    private function markAsCancel(Task $task)
    {
        if (! $task->canMarkAsCancel()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $task->cancelled_at = now()->toDateTimeString();
        $task->save();
    }

    private function markAsActive(Task $task)
    {
        if (! $task->canMarkAsActive()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $task->cancelled_at = null;
        $task->save();
    }

    private function moveToArchive(Task $task)
    {
        if (! $task->canMoveToArchive()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $task->archived_at = now()->toDateTimeString();
        $task->save();
    }

    private function moveFromArchive(Task $task)
    {
        if (! $task->canMoveFromArchive()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $task->archived_at = null;
        $task->save();
    }

    public function uploadMedia(Request $request, Task $task)
    {
        $task->updateMedia($request);
    }

    public function removeMedia(Task $task, string $uuid)
    {
        $media = Media::query()
            ->whereModelType($task->getModelName())
            ->where('status', 1)
            ->whereModelId($task->id)
            ->whereUuid($uuid)
            ->getOrFail(trans('general.file'));

        if (\Storage::exists($media->name)) {
            \Storage::delete($media->name);
        }

        $media->delete();
    }

    public function getRepeatPreRequisite(Request $request, Task $task): array
    {
        $frequencies = RepeatFrequency::getOptions();
        $days = Day::getOptions();
        $months = Month::getOptions();

        $dates = [];
        for ($i = 1; $i <= 31; $i++) {
            $dates[] = $i;
        }

        return compact('frequencies', 'days', 'months', 'dates');
    }

    public function updateRepeatation(Request $request, Task $task): void
    {
        if (! $request->boolean('should_repeat')) {
            $task->should_repeat = false;
            $task->save();

            return;
        }

        if ($request->start_date <= $task->start_date->value) {
            throw ValidationException::withMessages(['start_date' => trans('validation.after', ['attribute' => trans('task.repeat.props.start_date'), 'date' => $task->start_date->formatted])]);
        }

        if ($request->start_date < today()->toDateString()) {
            throw ValidationException::withMessages(['start_date' => trans('validation.after', ['attribute' => trans('task.repeat.props.start_date'), 'date' => CalHelper::showDate(today()->toDateString())])]);
        }

        if ($request->start_date == today()->toDateString()) {
            throw ValidationException::withMessages(['start_date' => trans('validation.after', ['attribute' => trans('task.repeat.props.start_date'), 'date' => trans('list.durations.today')])]);
        }

        \DB::beginTransaction();

        $task->should_repeat = $request->boolean('should_repeat');
        $task->repeatation = $task->should_repeat ? [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'frequency' => $request->frequency,
            'days' => $request->days,
            'dates' => $request->dates,
            'day_wise_count' => (int) $request->day_wise_count,
        ] : [];
        $task->save();

        $nextRepeateDate = (new GetNextRepeatDate)->execute($task->repeatation);

        $repeatation = $task->repeatation;
        $repeatation['next_repeat_date'] = $nextRepeateDate;
        $task->repeatation = $repeatation;
        $task->save();

        \DB::commit();
    }

    public function moveList(Request $request, Task $task): void
    {
        $taskList = Option::query()
            ->byTeam()
            ->whereType(OptionType::TASK_LIST->value)
            ->whereUuid($request->list_uuid)
            ->first();

        if (! $taskList) {
            throw ValidationException::withMessages(['message' => trans('task.list.could_not_perform_if_empty_list')]);
        }

        $task->list_id = $taskList?->id;
        $task->save();

        foreach ($request->item_uuids as $order => $uuid) {
            Task::query()
                ->byTeam()
                ->whereUuid($uuid)->update(['position' => $order]);
        }
    }

    public function reorder(Request $request): void
    {
        $request->validate(['uuids' => 'array|min:1']);

        foreach ($request->uuids as $order => $uuid) {
            Task::query()
                ->byTeam()
                ->whereUuid($uuid)->update(['position' => $order]);
        }
    }
}
