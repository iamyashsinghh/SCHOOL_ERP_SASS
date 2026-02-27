<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Services\Task\TaskListService;
use Illuminate\Http\Request;

class TaskExportController extends Controller
{
    public function __invoke(Request $request, TaskListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
