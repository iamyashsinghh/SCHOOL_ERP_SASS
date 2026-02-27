<?php

namespace App\Http\Controllers\Task;

use App\Services\Task\Dashboard\ChartService;
use App\Services\Task\Dashboard\FavoriteService;
use App\Services\Task\Dashboard\RecordService;
use App\Services\Task\Dashboard\StatService;
use Illuminate\Http\Request;

class DashboardController
{
    /**
     * Dashboard stats
     */
    public function stat(Request $request, StatService $service)
    {
        return $service->getData($request);
    }

    public function favorite(Request $request, FavoriteService $service)
    {
        return $service->getData($request);
    }

    public function chart(Request $request, ChartService $service)
    {
        return $service->getData($request);
    }

    public function record(Request $request, RecordService $service)
    {
        return $service->getData($request);
    }
}
