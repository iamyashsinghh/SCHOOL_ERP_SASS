<?php

namespace App\Services\Inventory;

use App\Contracts\ListGenerator;
use App\Http\Resources\Inventory\StockItemWithCopyResource;
use App\Models\Tenant\Inventory\StockItem;
use App\Models\Tenant\Inventory\StockItemCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class StockItemWithCopyListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'name'];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        return [];
    }

    public function filter(Request $request): Builder
    {
        $search = $request->query('search');
        $inventory = $request->query('inventory');
        $stockCategories = Str::toArray($request->query('stock_categories'));

        $bulkItems = StockItem::query()
            ->select(
                'stock_items.id',
                'stock_items.uuid as stock_item_uuid',
                'stock_items.name',
                'stock_items.code',
                'stock_items.tracking_type',
                \DB::raw('NULL as code_number'),
                \DB::raw('NULL as stock_item_copy_uuid')
            )
            ->join('stock_categories', 'stock_categories.id', '=', 'stock_items.stock_category_id')
            ->join('inventories', 'inventories.id', '=', 'stock_categories.inventory_id')
            ->where('inventories.uuid', $inventory)
            ->where('tracking_type', 'bulk')
            ->where('stock_items.name', 'like', "%{$search}%");

        $uniqueItems = StockItemCopy::query()
            ->join('stock_items', 'stock_items.id', '=', 'stock_item_copies.stock_item_id')
            ->select(
                'stock_items.id',
                'stock_items.uuid as stock_item_uuid',
                'stock_items.name',
                'stock_items.code',
                'stock_items.tracking_type',
                'stock_item_copies.code_number',
                'stock_item_copies.uuid as stock_item_copy_uuid'
            )
            ->join('stock_categories', 'stock_categories.id', '=', 'stock_items.stock_category_id')
            ->join('inventories', 'inventories.id', '=', 'stock_categories.inventory_id')
            ->where('inventories.uuid', $inventory)
            ->where('stock_items.tracking_type', 'unique')
            ->where(function ($q) use ($search) {
                $q->where('stock_items.name', 'like', "%{$search}%")
                    ->orWhere('stock_item_copies.code_number', 'like', "%{$search}%");
            });

        return $bulkItems->union($uniqueItems);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return StockItemWithCopyResource::collection($this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
