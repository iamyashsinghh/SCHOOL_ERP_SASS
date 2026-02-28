<?php

namespace App\Services\Inventory;

use App\Models\Tenant\Inventory\StockItem;
use App\Models\Tenant\Inventory\StockItemCopy;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Request;

class StockItemLabelService
{
    public function preRequisite(Request $request)
    {
        return [];
    }

    public function print(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
            'start_number' => ['required', 'integer'],
            'end_number' => ['required', 'integer'],
            'column' => ['required', 'integer'],
            'label_per_page' => ['required', 'integer'],
        ]);

        $stockItem = StockItem::query()
            ->where('code', $request->code)
            ->firstOrFail();

        $stockItemCopies = StockItemCopy::query()
            ->where('stock_item_id', $stockItem->id)
            ->whereBetween('number', [$request->start_number, $request->end_number])
            ->get();

        $stockItemCopies = $stockItemCopies->map(function ($stockItemCopy) {
            return [
                'number' => $stockItemCopy->number,
                'code_number' => $stockItemCopy->code_number,
                'name' => $stockItemCopy->item->name,
                'category' => $stockItemCopy->item->category->name,
                'qr_code' => (new QRCode)->render(
                    $stockItemCopy->code_number
                ),
            ];
        });

        $column = $request->query('column') ?? 1;
        $labelPerPage = $request->query('label_per_page') ?? 1;

        return view('print.inventory.stock-item.label', compact('stockItemCopies', 'column', 'labelPerPage'));
    }
}
