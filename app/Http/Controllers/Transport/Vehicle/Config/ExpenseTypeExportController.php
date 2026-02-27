<?php

namespace App\Http\Controllers\Transport\Vehicle\Config;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\Config\ExpenseTypeListService;
use Illuminate\Http\Request;

class ExpenseTypeExportController extends Controller
{
    public function __invoke(Request $request, ExpenseTypeListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
