<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\DayClosureListService;
use Illuminate\Http\Request;

class DayClosureExportController extends Controller
{
    public function __invoke(Request $request, DayClosureListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
