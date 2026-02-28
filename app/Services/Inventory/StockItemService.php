<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\ItemTrackingType;
use App\Enums\Inventory\ItemType;
use App\Enums\OptionType;
use App\Http\Resources\Asset\Building\RoomResource;
use App\Http\Resources\Inventory\InventoryResource;
use App\Http\Resources\Inventory\StockCategoryResource;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Asset\Building\Room;
use App\Models\Tenant\Inventory\Inventory;
use App\Models\Tenant\Inventory\StockBalance;
use App\Models\Tenant\Inventory\StockCategory;
use App\Models\Tenant\Inventory\StockItem;
use App\Models\Tenant\Inventory\StockItemCopy;
use App\Models\Tenant\Inventory\StockItemRecord;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockItemService
{
    public function preRequisite(Request $request)
    {
        $inventories = InventoryResource::collection(Inventory::query()
            ->byTeam()
            ->filterAccessible()
            ->get());

        $categories = StockCategoryResource::collection(StockCategory::query()
            ->byTeam()
            ->filterAccessible()
            ->get());

        $places = RoomResource::collection(Room::query()
            ->byTeam()
            ->withFloorAndBlock()
            ->get());

        $units = OptionResource::collection(Option::query()
            ->where('type', OptionType::UNIT)
            ->get());

        $types = ItemType::getOptions();

        $trackingTypes = ItemTrackingType::getOptions();

        return compact('inventories', 'categories', 'places', 'units', 'types', 'trackingTypes');
    }

    public function create(Request $request): StockItem
    {
        \DB::beginTransaction();

        $stockItem = StockItem::forceCreate($this->formatParams($request));

        if ($request->place_id) {
            $stockBalance = StockBalance::forceCreate([
                'place_type' => 'Room',
                'place_id' => $request->place_id,
                'stock_item_id' => $stockItem->id,
                'opening_quantity' => $request->quantity,
            ]);
        }

        \DB::commit();

        return $stockItem;
    }

    private function formatParams(Request $request, ?StockItem $stockItem = null): array
    {
        $formatted = [
            'name' => $request->name,
            'code' => Str::upper($request->code),
            'stock_category_id' => $request->stock_category_id,
            'unit' => $request->unit_name,
            'type' => $request->type,
            'tracking_type' => $request->tracking_type,
            'description' => $request->description,
        ];

        if (! $stockItem) {

        }

        return $formatted;
    }

    public function update(Request $request, StockItem $stockItem): void
    {
        $stockItemCopyExists = StockItemCopy::query()
            ->where('stock_item_id', $stockItem->id)
            ->exists();

        if ($stockItem->tracking_type->value != $request->tracking_type) {
            throw ValidationException::withMessages(['message' => trans('inventory.stock_item.could_not_change_tracking_type')]);
        }

        if ($stockItem->tracking_type == ItemTrackingType::UNIQUE && $stockItemCopyExists && $stockItem->code != $request->code) {
            throw ValidationException::withMessages(['message' => trans('inventory.stock_item.could_not_change_code')]);
        }

        $this->validateChangeInventory($request, $stockItem);

        \DB::beginTransaction();

        $stockItem->forceFill($this->formatParams($request, $stockItem))->save();

        if ($stockItem->is_quantity_editable) {
            $stockBalance = StockBalance::query()
                ->whereStockItemId($stockItem->id)
                ->first();

            if (! $stockBalance) {
                if ($request->place_id) {
                    $stockBalance = StockBalance::forceCreate([
                        'place_type' => 'Room',
                        'place_id' => $request->place_id,
                        'stock_item_id' => $stockItem->id,
                        'opening_quantity' => $request->quantity,
                    ]);
                }
            } else {
                $stockBalance->update([
                    'place_id' => $request->place_id,
                    'opening_quantity' => $request->quantity,
                ]);
            }
        }

        \DB::commit();
    }

    private function validateChangeInventory(Request $request, StockItem $stockItem)
    {
        $newStockCategory = StockCategory::query()
            ->whereId($request->stock_category_id)
            ->firstOrFail();

        if ($newStockCategory->inventory_id == $stockItem->category->inventory_id) {
            return;
        }

        $stockTransactionExists = StockItemRecord::query()
            ->where('stock_item_id', $stockItem->id)
            ->exists();

        if ($stockTransactionExists) {
            throw ValidationException::withMessages(['message' => trans('inventory.stock_item.could_not_change_inventory_after_transaction')]);
        }
    }

    public function deletable(StockItem $stockItem): void
    {
        $transactionExists = \DB::table('stock_item_records')
            ->whereStockItemId($stockItem->id)
            ->exists();

        if ($transactionExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('inventory.stock_item.stock_item'), 'dependency' => trans('inventory.transaction')])]);
        }
    }
}
