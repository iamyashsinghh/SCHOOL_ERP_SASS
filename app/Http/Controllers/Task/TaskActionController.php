<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\RepeatRequest;
use App\Models\Task\Task;
use App\Services\Task\TaskActionService;
use Illuminate\Http\Request;

class TaskActionController extends Controller
{
    public function __construct()
    {
        //
    }

    public function updateTags(Request $request, string $task, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('update', $task);

        $service->updateTags($request, $task);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.task')]),
        ]);
    }

    public function toggleFavorite(string $task, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureIsMember();

        $service->toggleFavorite($task);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.task')]),
        ]);
    }

    public function updateStatus(Request $request, string $task, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureIsMember();

        $task->load([
            'checklists:id,task_id,completed_at',
        ]);

        $service->updateStatus($request, $task);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.task')]),
        ]);
    }

    public function uploadMedia(Request $request, string $task, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('media');

        $service->uploadMedia($request, $task);

        return response()->success([
            'message' => trans('global.uploaded', ['attribute' => trans('general.file')]),
        ]);
    }

    public function removeMedia(string $task, string $uuid, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('media');

        $service->removeMedia($task, $uuid);

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('general.file')]),
        ]);
    }

    public function repeatPreRequisite(Request $request, string $task, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        return response()->ok($service->getRepeatPreRequisite($request, $task));
    }

    public function updateRepeatation(RepeatRequest $request, string $task, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureIsActionable();

        $service->updateRepeatation($request, $task);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('task.task')]),
        ]);
    }

    public function moveList(Request $request, TaskActionService $service)
    {
        $task = Task::findByUuidOrFail($request->uuid);

        $task->ensureCanManage('task_list');

        $service->moveList($request, $task);

        return response()->ok([]);
    }

    public function reorder(Request $request, TaskActionService $service)
    {
        $service->reorder($request);

        return response()->ok([]);
    }
}
