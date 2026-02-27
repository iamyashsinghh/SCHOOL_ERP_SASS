<?php

namespace App\Actions\Task;

use App\Models\Task\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReplicateChecklist
{
    public function execute(Task $task, Collection $checklists, $startDate = null): void
    {
        foreach ($checklists as $checklist) {
            $newChecklist = $checklist->replicate();
            $newChecklist->uuid = (string) Str::uuid();
            $newChecklist->task_id = $task->id;

            if ($checklist->due_date) {
                $checklistDueAfter = abs(Carbon::parse($startDate)->diffInDays(Carbon::parse($checklist->due_date->value)));
                $newChecklist->due_date = Carbon::parse($task->start_date->value)->addDays($checklistDueAfter)->toDateString();
            }

            $newChecklist->completed_at = null;
            $newChecklist->save();
        }
    }
}
