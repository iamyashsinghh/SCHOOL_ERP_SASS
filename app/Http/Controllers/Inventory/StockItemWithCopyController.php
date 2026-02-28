<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Inventory\StockItem;
use App\Services\Inventory\StockItemWithCopyListService;
use App\Services\Inventory\StockItemWithCopyService;
use Illuminate\Http\Request;

class StockItemWithCopyController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, StockItemWithCopyService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, StockItemWithCopyListService $service)
    {
        $this->authorize('viewAny', StockItem::class);

        return $service->paginate($request);
    }
}
