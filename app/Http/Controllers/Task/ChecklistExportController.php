<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Task\Task;
use App\Services\Task\ChecklistListService;
use Illuminate\Http\Request;

class ChecklistExportController extends Controller
{
    public function __invoke(Request $request, string $task, ChecklistListService $service)
    {
        $task = Task::findByUuidOrFail($task);

        $list = $service->list($request, $task);

        return $service->export($list);
    }
}
