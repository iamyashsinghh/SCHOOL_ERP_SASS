<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\MemberRequest;
use App\Http\Resources\Task\MemberResource;
use App\Models\Task\Task;
use App\Services\Task\MemberListService;
use App\Services\Task\MemberService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function index(Request $request, string $task, MemberListService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('view', $task);

        return $service->paginate($request, $task);
    }

    public function store(MemberRequest $request, string $task, MemberService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('member');

        $service->create($request, $task);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('task.member.member')]),
        ]);
    }

    public function show(Request $request, string $task, string $member, MemberService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $this->authorize('view', $task);

        $member = $service->findByUuidOrFail($task, $member);

        return MemberResource::make($member);
    }

    public function destroy(string $task, string $member, MemberService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $task->ensureCanManage('member');

        $member = $service->findByUuidOrFail($task, $member);

        $service->deletable($task, $member);

        $member->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('task.member.member')]),
        ]);
    }
}
