<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\TaxListService;
use Illuminate\Http\Request;

class TaxExportController extends Controller
{
    public function __invoke(Request $request, TaxListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
