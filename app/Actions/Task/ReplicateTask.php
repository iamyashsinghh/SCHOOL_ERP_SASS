<?php

namespace App\Actions\Task;

use App\Models\Tenant\Task\Task;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ReplicateTask
{
    public function execute(Task $task): Task
    {
        $repeatation = $task->repeatation;
        $startDate = Arr::get($repeatation, 'next_repeat_date');
        $dueAfter = abs(Carbon::parse($task->start_date->value)->diffInDays(Carbon::parse($task->due_date->value)));

        $newTask = $task->replicate();
        $newTask->uuid = (string) Str::uuid();

        $codeNumberDetail = (new GenerateCodeNumber)->execute($task);

        $newTask->number_format = Arr::get($codeNumberDetail, 'number_format');
        $newTask->number = Arr::get($codeNumberDetail, 'number');
        $newTask->code_number = Arr::get($codeNumberDetail, 'code_number');
        $newTask->progress = 0;
        $newTask->start_date = $startDate;
        $newTask->due_date = Carbon::parse($startDate)->addDays($dueAfter)->toDateString();
        $newTask->completed_at = null;
        $newTask->archived_at = null;
        $newTask->should_repeat = 0;
        $newTask->repeatation = null;

        $meta = $task->meta;
        $meta['media_token'] = (string) Str::uuid();
        $meta['repeated_task_uuid'] = $task->uuid;
        $newTask->meta = $meta;

        $newTask->save();

        return $newTask;
    }
}
