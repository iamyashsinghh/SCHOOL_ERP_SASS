<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Inventory\StockItem;
use App\Services\Inventory\StockItemCopyListService;
use App\Services\Inventory\StockItemCopyService;
use Illuminate\Http\Request;

class StockItemCopyController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(StockItemCopyService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, StockItemCopyListService $service)
    {
        $this->authorize('viewAny', StockItem::class);

        return $service->paginate($request);
    }
}
