<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ChecklistRequest;
use App\Http\Resources\Task\ChecklistResource;
use App\Models\Task\Task;
use App\Services\Task\ChecklistListService;
use App\Services\Task\ChecklistService;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function index(Request $request, string $task, ChecklistListService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('view', $task);

        return $service->paginate($request, $task);
    }

    public function store(ChecklistRequest $request, string $task, ChecklistService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('checklist');

        $checklist = $service->create($request, $task);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('task.checklist.checklist')]),
            'checklist' => ChecklistResource::make($checklist),
        ]);
    }

    public function show(Request $request, string $task, string $checklist, ChecklistService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('view', $task);

        $checklist = $service->findByUuidOrFail($task, $checklist);

        return ChecklistResource::make($checklist);
    }

    public function update(ChecklistRequest $request, string $task, string $checklist, ChecklistService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('checklist');

        $checklist = $service->findByUuidOrFail($task, $checklist);

        $service->update($request, $task, $checklist);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.checklist.checklist')]),
        ]);
    }

    public function destroy(string $task, string $checklist, ChecklistService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('checklist');

        $checklist = $service->findByUuidOrFail($task, $checklist);

        $service->deletable($task, $checklist);

        $checklist->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('task.checklist.checklist')]),
        ]);
    }
}
