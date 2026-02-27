<?php

namespace App\Http\Controllers\Student\Report;

use App\Http\Controllers\Controller;
use App\Services\Student\Report\DailyAccessReportListService;
use App\Services\Student\Report\DailyAccessReportService;
use Illuminate\Http\Request;

class DailyAccessReportController extends Controller
{
    public function preRequisite(Request $request, DailyAccessReportService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function fetch(Request $request, DailyAccessReportService $reportService, DailyAccessReportListService $reportListService)
    {
        if (auth()->user()->can('student:admin-access') && ! $request->has('batch')) {
            return $reportService->fetch($request);
        }

        return $reportListService->filter($request);
    }
}
