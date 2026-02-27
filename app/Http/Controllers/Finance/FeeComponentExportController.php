<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FeeComponentListService;
use Illuminate\Http\Request;

class FeeComponentExportController extends Controller
{
    public function __invoke(Request $request, FeeComponentListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
