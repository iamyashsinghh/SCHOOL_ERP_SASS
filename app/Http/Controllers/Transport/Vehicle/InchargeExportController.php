<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\InchargeListService;
use Illuminate\Http\Request;

class InchargeExportController extends Controller
{
    public function __invoke(Request $request, InchargeListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
