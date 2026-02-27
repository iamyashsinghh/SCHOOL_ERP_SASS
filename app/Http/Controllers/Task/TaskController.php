<?php

namespace App\Http\Controllers\Task;

use App\Actions\Task\CreateTask;
use App\Actions\Task\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskRequest;
use App\Http\Resources\Task\TaskResource;
use App\Models\Task\Task;
use App\Services\Task\TaskListService;
use App\Services\Task\TaskService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, TaskService $service)
    {
        $this->authorize('preRequisite', Task::class);

        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, TaskListService $service)
    {
        $this->authorize('viewAny', Task::class);

        return $service->paginate($request);
    }

    public function store(TaskRequest $request, CreateTask $action)
    {
        $this->authorize('create', Task::class);

        $task = $action->execute($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('task.task')]),
            'task' => TaskResource::make($task),
        ]);
    }

    public function show(Request $request, string $task, TaskService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('view', $task);

        $task->load([
            'priority', 'category', 'media', 'tags', 'checklists:id,task_id,completed_at',
        ]);

        $request->merge(['detail' => true, 'has_custom_fields' => true]);

        return TaskResource::make($task);
    }

    public function update(TaskRequest $request, string $task, UpdateTask $action)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('update', $task);

        $task->ensureIsEditable();

        $action->execute($request, $task);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.task')]),
        ]);
    }

    public function destroy(string $task, TaskService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('delete', $task);

        $service->deletable($task);

        $task->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('task.task')]),
        ]);
    }

    public function downloadMedia(string $task, string $uuid, TaskService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('view', $task);

        return $task->downloadMedia($uuid);
    }
}
