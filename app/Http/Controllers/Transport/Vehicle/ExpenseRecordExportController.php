<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\ExpenseRecordListService;
use Illuminate\Http\Request;

class ExpenseRecordExportController extends Controller
{
    public function __invoke(Request $request, ExpenseRecordListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
