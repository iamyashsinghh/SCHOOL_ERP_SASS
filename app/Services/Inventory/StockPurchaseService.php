<?php

namespace App\Services\Inventory;

use App\Enums\Finance\TransactionType;
use App\Enums\Inventory\ItemTrackingType;
use App\Http\Resources\Asset\Building\RoomResource;
use App\Http\Resources\Finance\LedgerResource;
use App\Http\Resources\Inventory\InventoryResource;
use App\Models\Tenant\Asset\Building\Room;
use App\Models\Tenant\Finance\Ledger;
use App\Models\Tenant\Inventory\Inventory;
use App\Models\Tenant\Inventory\StockBalance;
use App\Models\Tenant\Inventory\StockItem;
use App\Models\Tenant\Inventory\StockItemCopy;
use App\Models\Tenant\Inventory\StockItemCopyRecord;
use App\Models\Tenant\Inventory\StockItemRecord;
use App\Models\Tenant\Inventory\StockPurchase;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class StockPurchaseService
{
    use FormatCodeNumber;

    private function codeNumber(): array
    {
        $numberPrefix = config('config.inventory.stock_purchase_number_prefix');
        $numberSuffix = config('config.inventory.stock_purchase_number_suffix');
        $digit = config('config.inventory.stock_purchase_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) StockPurchase::query()
            ->byTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(Request $request)
    {
        $inventories = InventoryResource::collection(Inventory::query()
            ->byTeam()
            ->get());

        $vendors = LedgerResource::collection(Ledger::query()
            ->byTeam()
            ->subType('vendor')
            ->get());

        $places = RoomResource::collection(Room::query()
            ->withFloorAndBlock()
            ->get());

        return compact('inventories', 'vendors', 'places');
    }

    public function create(Request $request): StockPurchase
    {
        // throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);

        \DB::beginTransaction();

        $stockPurchase = StockPurchase::forceCreate($this->formatParams($request));

        if ($request->boolean('has_items')) {
            $this->updateItems($request, $stockPurchase);
        }

        $ledger = $request->ledger;
        $ledger->updateSecondaryBalance(TransactionType::RECEIPT, $stockPurchase->total->value);

        $stockPurchase->addMedia($request);

        foreach ($request->items as $item) {
            $this->createStockItemCopies($stockPurchase, $item);
        }

        \DB::commit();

        return $stockPurchase;
    }

    private function createStockItemCopies(StockPurchase $stockPurchase, array $item): void
    {
        if (Arr::get($item, 'tracking_type') != ItemTrackingType::UNIQUE->value) {
            return;
        }

        $stockItemCopyNumber = StockItemCopy::query()
            ->where('stock_item_id', Arr::get($item, 'stock_item_id'))
            ->max('number') + 1;

        $stockItemCopyIds = [];
        for ($i = 0; $i < Arr::get($item, 'quantity'); $i++) {
            $stockItemCopy = StockItemCopy::forceCreate([
                'number' => $stockItemCopyNumber,
                'code_number' => Arr::get($item, 'code').'-'.($stockItemCopyNumber),
                'stock_item_id' => Arr::get($item, 'stock_item_id'),
                'vendor' => $stockPurchase->vendor?->name,
                'invoice_date' => $stockPurchase->date->value,
                'invoice_number' => $stockPurchase->voucher_number,
                'price' => Arr::get($item, 'unit_price'),
                'place_type' => 'Room',
                'place_id' => $stockPurchase->place_id,
                'meta' => [
                    'hsn' => Arr::get($item, 'hsn'),
                ],
            ]);

            $stockItemCopyIds[] = $stockItemCopy->id;

            $stockItemCopyNumber++;
        }

        $stockItemRecord = StockItemRecord::query()
            ->where('stock_item_id', Arr::get($item, 'stock_item_id'))
            ->where('model_type', 'StockPurchase')
            ->where('model_id', $stockPurchase->id)
            ->first();

        foreach ($stockItemCopyIds as $stockItemCopyId) {
            StockItemCopyRecord::forceCreate([
                'stock_item_record_id' => $stockItemRecord->id,
                'stock_item_copy_id' => $stockItemCopyId,
            ]);
        }
    }

    private function updateItems(Request $request, StockPurchase $stockPurchase): void
    {
        $stockItemIds = [];
        foreach ($request->items as $item) {
            $stockItemIds[] = Arr::get($item, 'stock_item_id');

            $stockItemRecord = StockItemRecord::firstOrCreate([
                'model_type' => 'StockPurchase',
                'model_id' => $stockPurchase->id,
                'stock_item_id' => Arr::get($item, 'stock_item_id'),
            ]);

            $stockBalance = StockBalance::query()
                ->wherePlaceType($stockPurchase->place_type)
                ->wherePlaceId($stockPurchase->place_id)
                ->whereStockItemId($stockItemRecord->stock_item_id)
                ->first();

            $stockItem = StockItem::find(Arr::get($item, 'stock_item_id'));

            $stockItemRecord->uuid = Arr::get($item, 'uuid');
            $stockItemRecord->stock_item_id = Arr::get($item, 'stock_item_id');
            $stockItemRecord->description = Arr::get($item, 'description');
            $stockItemRecord->quantity = Arr::get($item, 'quantity');
            $stockItemRecord->unit_price = Arr::get($item, 'unit_price');
            $stockItemRecord->amount = Arr::get($item, 'amount');
            $stockItemRecord->save();

            if (! $stockBalance) {
                $stockBalance = StockBalance::forceCreate([
                    'place_type' => $stockPurchase->place_type,
                    'place_id' => $stockPurchase->place_id,
                    'stock_item_id' => $stockItemRecord->stock_item_id,
                    'current_quantity' => $stockItemRecord->quantity,
                ]);
            } else {
                $stockBalance->current_quantity += $stockItemRecord->quantity;
                $stockBalance->save();
            }
        }

        StockItemRecord::query()
            ->whereModelType('StockPurchase')
            ->whereModelId($stockPurchase->id)
            ->whereNotIn('stock_item_id', $stockItemIds)
            ->delete();
    }

    private function reverseBalance(StockPurchase $stockPurchase): void
    {
        foreach ($stockPurchase->items as $item) {
            $stockBalance = StockBalance::query()
                ->wherePlaceType($stockPurchase->place_type)
                ->wherePlaceId($stockPurchase->place_id)
                ->whereStockItemId($item->stock_item_id)
                ->first();

            if ($stockBalance) {
                $stockBalance->current_quantity -= $item->quantity;
                $stockBalance->save();
            }
        }
    }

    private function formatParams(Request $request, ?StockPurchase $stockPurchase = null): array
    {
        $formatted = [
            'date' => $request->date,
            'inventory_id' => $request->inventory_id,
            'vendor_id' => $request->vendor_id,
            'place_type' => 'Room',
            'place_id' => $request->place_id,
            'voucher_number' => $request->voucher_number,
            'total' => $request->total,
            'description' => $request->description,
        ];

        $meta = $stockPurchase?->meta ?? [];
        $meta['has_items'] = $request->boolean('has_items');
        $formatted['meta'] = $meta;

        if (! $stockPurchase) {
            $codeNumberDetail = $this->codeNumber();

            $formatted['number_format'] = Arr::get($codeNumberDetail, 'number_format');
            $formatted['number'] = Arr::get($codeNumberDetail, 'number');
            $formatted['code_number'] = Arr::get($codeNumberDetail, 'code_number');
        }

        return $formatted;
    }

    private function validateTrackingItems(Request $request, StockPurchase $stockPurchase): array
    {
        $newStockItemCopies = [];

        $stockPurchase->load('items.item');

        $existingStockPurchasedTrackingTypeItemIds = [];
        foreach ($stockPurchase->items as $item) {
            if ($item->item->tracking_type == ItemTrackingType::UNIQUE) {
                $existingStockPurchasedTrackingTypeItemIds[] = $item->stock_item_id;
            }
        }

        $requestItemIds = [];
        $newStockPurchasedTrackingTypeItemIds = [];
        foreach ($request->items as $item) {
            $stockPurchaseItem = $stockPurchase->items->firstWhere('stock_item_id', Arr::get($item, 'stock_item_id'));

            $requestItemIds[] = Arr::get($item, 'stock_item_id');

            if (Arr::get($item, 'tracking_type') == ItemTrackingType::UNIQUE->value) {
                $newStockPurchasedTrackingTypeItemIds[] = $item['stock_item_id'];
            }

            if (! $stockPurchaseItem && Arr::get($item, 'tracking_type') == ItemTrackingType::UNIQUE->value) {
                $newStockItemCopies[] = [
                    'stock_item_id' => Arr::get($item, 'stock_item_id'),
                    'code' => Arr::get($item, 'code'),
                    'unit_price' => Arr::get($item, 'unit_price'),
                    'quantity' => Arr::get($item, 'quantity'),
                    'tracking_type' => Arr::get($item, 'tracking_type'),
                ];
            } elseif ($stockPurchaseItem?->item?->tracking_type == ItemTrackingType::UNIQUE) {
                if ($stockPurchaseItem->quantity > Arr::get($item, 'quantity')) {
                    throw ValidationException::withMessages(['message' => trans('inventory.stock_purchase.could_not_change_quantity_for_unique_item')]);
                } elseif ($stockPurchaseItem->quantity < Arr::get($item, 'quantity')) {
                    $newStockItemCopies[] = [
                        'stock_item_id' => Arr::get($item, 'stock_item_id'),
                        'code' => Arr::get($item, 'code'),
                        'unit_price' => Arr::get($item, 'unit_price'),
                        'quantity' => Arr::get($item, 'quantity') - $stockPurchaseItem->quantity,
                        'tracking_type' => Arr::get($item, 'tracking_type'),
                    ];
                }
            }
        }

        if (array_diff($existingStockPurchasedTrackingTypeItemIds, $requestItemIds)) {
            throw ValidationException::withMessages(['message' => trans('inventory.stock_purchase.could_not_alter_item_for_tracking_type_item')]);
        }

        return $newStockItemCopies;
    }

    public function update(Request $request, StockPurchase $stockPurchase): void
    {
        $newStockItemCopies = $this->validateTrackingItems($request, $stockPurchase);

        $updateLedger = false;
        if ($stockPurchase->total->value != $request->total || $stockPurchase->vendor_id != $request->vendor_id) {
            $updateLedger = true;
        }

        \DB::beginTransaction();

        if ($updateLedger) {
            $ledger = $stockPurchase->vendor;
            $ledger->reverseSecondaryBalance(TransactionType::RECEIPT, $stockPurchase->total->value);
        }

        $this->reverseBalance($stockPurchase);

        $stockPurchase->forceFill($this->formatParams($request, $stockPurchase))->save();

        $this->updateItems($request, $stockPurchase);

        if ($updateLedger) {
            $ledger = $request->ledger;
            $ledger->updateSecondaryBalance(TransactionType::RECEIPT, $request->total);
        }

        foreach ($newStockItemCopies as $item) {
            $this->createStockItemCopies($stockPurchase, $item);
        }

        $stockPurchase->updateMedia($request);

        \DB::commit();
    }

    public function deletable(StockPurchase $stockPurchase): void
    {
        $stockPurchaseExists = StockPurchase::query()
            ->where('id', '!=', $stockPurchase->id)
            ->where('date', '>=', $stockPurchase->date->value)
            ->exists();

        if ($stockPurchaseExists) {
            throw ValidationException::withMessages(['message' => trans('inventory.stock_purchase.could_not_delete_if_purchase_exists_after_this_date')]);
        }
    }

    public function delete(StockPurchase $stockPurchase): void
    {
        \DB::beginTransaction();

        $stockItemCopyRecords = StockItemCopyRecord::query()
            ->whereIn('stock_item_record_id', $stockPurchase->items->pluck('id'))
            ->get();

        $stockItemCopyIds = $stockItemCopyRecords->pluck('stock_item_copy_id');

        StockItemCopy::query()
            ->whereIn('id', $stockItemCopyIds)
            ->delete();

        $stockItemCopyRecords->each(fn (StockItemCopyRecord $stockItemCopyRecord) => $stockItemCopyRecord->delete());

        foreach ($stockPurchase->items as $item) {
            $stockBalance = StockBalance::query()
                ->wherePlaceType($stockPurchase->place_type)
                ->wherePlaceId($stockPurchase->place_id)
                ->whereStockItemId($item->stock_item_id)
                ->first();

            if ($stockBalance) {
                $stockBalance->current_quantity -= $item->quantity;
                $stockBalance->save();
            }

            $item->delete();
        }

        $ledger = $stockPurchase->vendor;
        $ledger->reverseSecondaryBalance(TransactionType::RECEIPT, $stockPurchase->total->value);

        $stockPurchase->delete();

        \DB::commit();
    }
}
