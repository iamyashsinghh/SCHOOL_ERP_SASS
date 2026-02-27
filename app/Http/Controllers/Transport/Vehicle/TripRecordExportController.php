<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\TripRecordListService;
use Illuminate\Http\Request;

class TripRecordExportController extends Controller
{
    public function __invoke(Request $request, TripRecordListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
