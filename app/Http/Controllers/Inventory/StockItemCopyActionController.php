<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Inventory\StockItem;
use App\Models\Tenant\Inventory\StockItemCopy;
use App\Services\Inventory\StockItemCopyActionService;
use Illuminate\Http\Request;

class StockItemCopyActionController extends Controller
{
    public function preRequisite(Request $request, StockItemCopy $stockItemCopy, StockItemCopyActionService $service)
    {
        $this->authorize('view', StockItem::class);

        return response()->ok($service->preRequisite($request, $stockItemCopy));
    }

    public function updateBulkCondition(Request $request, StockItemCopyActionService $service)
    {
        $this->authorize('bulkUpdate', StockItem::class);

        $count = $service->updateBulkCondition($request);

        return response()->success([
            'message' => trans('global.updated_with_count', ['attribute' => trans('inventory.stock_item.stock_item'), 'count' => $count]),
        ]);
    }

    public function updateBulkStatus(Request $request, StockItemCopyActionService $service)
    {
        $this->authorize('bulkUpdate', StockItem::class);

        $count = $service->updateBulkStatus($request);

        return response()->success([
            'message' => trans('global.updated_with_count', ['attribute' => trans('inventory.stock_item.stock_item'), 'count' => $count]),
        ]);
    }

    public function updateBulkTags(Request $request, StockItemCopyActionService $service)
    {
        $this->authorize('bulkUpdate', StockItem::class);

        $service->updateBulkTags($request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('inventory.stock_item.stock_item')]),
        ]);
    }
}
