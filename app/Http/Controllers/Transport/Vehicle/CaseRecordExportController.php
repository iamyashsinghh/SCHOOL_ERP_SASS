<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\CaseRecordListService;
use Illuminate\Http\Request;

class CaseRecordExportController extends Controller
{
    public function __invoke(Request $request, CaseRecordListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
