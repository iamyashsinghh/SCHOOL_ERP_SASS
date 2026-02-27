<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StockItemLabelService;
use Illuminate\Http\Request;

class StockItemLabelController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, StockItemLabelService $service)
    {
        return $service->preRequisite($request);
    }

    public function print(Request $request, StockItemLabelService $service)
    {
        return $service->print($request);
    }
}
