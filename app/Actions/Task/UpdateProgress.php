<?php

namespace App\Actions\Task;

use App\Models\Task\Checklist;
use App\Models\Task\Task;

class UpdateProgress
{
    public function execute(int $taskId): void
    {
        $task = Task::find($taskId);

        $checklists = Checklist::query()
            ->whereTaskId($task->id)
            ->get();

        $totalChecklist = $checklists->count();
        $completedChecklist = $checklists->filter(function ($checklist) {
            return ! empty($checklist->completed_at->value);
        })->count();

        $task->progress = 0;

        $meta = $task->meta;
        $meta['has_progress'] = false;

        if ($checklists->count()) {
            $task->progress = round(($completedChecklist / $totalChecklist * 100), 2);
            $meta['has_progress'] = true;
        }

        $task->meta = $meta;
        $task->save();
    }
}
