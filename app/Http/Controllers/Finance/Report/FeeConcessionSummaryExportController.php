<?php

namespace App\Http\Controllers\Finance\Report;

use App\Http\Controllers\Controller;
use App\Services\Finance\Report\FeeConcessionSummaryListService;
use Illuminate\Http\Request;

class FeeConcessionSummaryExportController extends Controller
{
    public function __invoke(Request $request, FeeConcessionSummaryListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
