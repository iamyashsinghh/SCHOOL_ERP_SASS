<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FeeStructureComponentListService;
use Illuminate\Http\Request;

class FeeStructureComponentExportController extends Controller
{
    public function __invoke(Request $request, FeeStructureComponentListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
