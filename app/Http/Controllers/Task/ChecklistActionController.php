<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Task\Checklist;
use App\Models\Task\Task;
use App\Services\Task\ChecklistActionService;
use Illuminate\Http\Request;

class ChecklistActionController extends Controller
{
    public function __construct()
    {
        //
    }

    public function toggleStatus(Request $request, string $task, string $checklist, ChecklistActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('checklist');

        $checklist = Checklist::query()
            ->whereTaskId($task->id)
            ->findByUuidOrFail($checklist);

        $service->toggleStatus($request, $task, $checklist);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.checklist.props.status')]),
        ]);
    }
}
