<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StockItemCopyListService;
use Illuminate\Http\Request;

class StockItemCopyExportController extends Controller
{
    public function __invoke(Request $request, StockItemCopyListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
