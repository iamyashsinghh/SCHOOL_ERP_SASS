<?php

namespace App\Http\Controllers\Finance\Report;

use App\Http\Controllers\Controller;
use App\Services\Finance\Report\FeeConcessionSummaryListService;
use App\Services\Finance\Report\FeeConcessionSummaryService;
use Illuminate\Http\Request;

class FeeConcessionSummaryController extends Controller
{
    public function preRequisite(Request $request, FeeConcessionSummaryService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function fetch(Request $request, FeeConcessionSummaryListService $service)
    {
        return $service->paginate($request);
    }
}
