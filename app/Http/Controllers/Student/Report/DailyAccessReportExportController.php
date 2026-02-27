<?php

namespace App\Http\Controllers\Student\Report;

use App\Http\Controllers\Controller;
use App\Services\Student\Report\DailyAccessReportListService;
use Illuminate\Http\Request;

class DailyAccessReportExportController extends Controller
{
    public function __invoke(Request $request, DailyAccessReportListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
