<?php

namespace App\Services\Task;

use App\Models\Task\Checklist;
use App\Models\Task\Task;
use Illuminate\Http\Request;

class ChecklistActionService
{
    public function toggleStatus(Request $request, Task $task, Checklist $checklist)
    {
        $checklist->completed_at = ! $checklist->completed_at->value ? now()->toDateTimeString() : null;
        $checklist->save();
    }
}
