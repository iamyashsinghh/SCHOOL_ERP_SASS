<?php

namespace App\Services\Task;

use App\Enums\CustomFieldForm;
use App\Enums\OptionType;
use App\Http\Resources\CustomFieldResource;
use App\Http\Resources\OptionResource;
use App\Models\CustomField;
use App\Models\Option;
use App\Models\Task\Task;
use Illuminate\Http\Request;

class TaskService
{
    public function preRequisite(Request $request): array
    {
        $categories = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TASK_CATEGORY->value)
            ->orderBy('meta->position', 'asc')
            ->get());

        $priorities = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TASK_PRIORITY->value)
            ->orderBy('meta->position', 'asc')
            ->get());

        $lists = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TASK_LIST->value)
            ->orderBy('meta->position', 'asc')
            ->get());

        $taskLists = $lists;

        $customFields = CustomFieldResource::collection(CustomField::query()
            ->byTeam()
            ->where('form', CustomFieldForm::TASK->value)
            ->get());

        return compact('lists', 'categories', 'priorities', 'taskLists', 'customFields');
    }

    public function deletable(Task $task): void
    {
        $task->ensureIsDeletable();
    }
}
