<?php

namespace App\Http\Requests\Inventory;

use App\Models\Asset\Building\Room;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\StockItemCopy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'from_place' => 'required|uuid',
            'to_place' => 'required|uuid',
            'description' => ['nullable', 'min:2', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.uuid' => ['required', 'uuid', 'distinct'],
            'items.*.item' => ['required', 'array'],
            'items.*.item.uuid' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.description' => ['nullable', 'min:2', 'max:100'],
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {

            $stockTransferUuid = $this->route('stock_transfer');

            $inventory = Inventory::query()
                ->byTeam()
                ->filterAccessible()
                ->where('uuid', $this->inventory)
                ->getOrFail(__('inventory.inventory'), 'inventory');

            $fromPlace = Room::query()
                ->withFloorAndBlock()
                ->where('rooms.uuid', $this->from_place)
                ->getOrFail(__('inventory.stock_transfer.props.from_place'), 'from_place');

            $toPlace = Room::query()
                ->withFloorAndBlock()
                ->where('rooms.uuid', $this->to_place)
                ->getOrFail(__('inventory.stock_transfer.props.to_place'), 'to_place');

            if ($fromPlace->id == $toPlace->id) {
                throw ValidationException::withMessages(['from_place' => trans('inventory.stock_transfer.from_to_same')]);
            }

            $inputBulkStockItems = collect($this->items)->where('item.tracking_type.value', 'bulk')->pluck('item.uuid');
            $inputUniqueStockItems = collect($this->items)->where('item.tracking_type.value', 'unique')->pluck('item.uuid');

            $stockItems = StockItem::query()
                ->select('stock_items.uuid', 'stock_items.id')
                ->join('stock_categories', 'stock_categories.id', '=', 'stock_items.stock_category_id')
                ->join('inventories', 'inventories.id', '=', 'stock_categories.inventory_id')
                ->where('inventories.uuid', $this->inventory)
                ->where('stock_items.tracking_type', 'bulk')
                ->whereIn('stock_items.uuid', $inputBulkStockItems)
                ->get();

            $stockItemCopies = StockItemCopy::query()
                ->select('stock_item_copies.uuid', 'stock_item_copies.id', 'stock_item_copies.stock_item_id')
                ->join('stock_items', 'stock_items.id', '=', 'stock_item_copies.stock_item_id')
                ->join('stock_categories', 'stock_categories.id', '=', 'stock_items.stock_category_id')
                ->join('inventories', 'inventories.id', '=', 'stock_categories.inventory_id')
                ->where('inventories.uuid', $this->inventory)
                ->where('stock_items.tracking_type', 'unique')
                ->whereIn('stock_item_copies.uuid', $inputUniqueStockItems)
                ->get();

            $newItems = [];
            foreach ($this->items as $index => $item) {
                $selectedItemId = null;
                $selectedItemCopyId = null;

                $quantity = round(Arr::get($item, 'quantity', 1), 2);

                if (Arr::get($item, 'item.tracking_type.value') == 'bulk') {
                    $selectedItemId = $stockItems->where('uuid', Arr::get($item, 'item.uuid'))->first()?->id;

                    if (! $selectedItemId) {
                        throw ValidationException::withMessages(['items.'.$index.'.item' => trans('validation.exists', ['attribute' => __('inventory.stock_item.stock_item')])]);
                    }
                } else {
                    $selectedItemCopy = $stockItemCopies->where('uuid', Arr::get($item, 'item.uuid'))->first();

                    $selectedItemCopyId = $selectedItemCopy?->id;
                    $selectedItemId = $selectedItemCopy?->stock_item_id;

                    if (! $selectedItemCopyId) {
                        throw ValidationException::withMessages(['items.'.$index.'.item' => trans('validation.exists', ['attribute' => __('inventory.stock_item.copy.copy')])]);
                    }

                    $quantity = 1;
                }

                $newItems[] = [
                    'uuid' => (string) Str::uuid(),
                    'stock_item_id' => $selectedItemId,
                    'stock_item_copy_id' => $selectedItemCopyId,
                    'quantity' => $quantity,
                    'description' => Arr::get($item, 'description'),
                ];
            }

            $this->merge([
                'inventory_id' => $inventory?->id,
                'from_id' => $fromPlace?->id,
                'to_id' => $toPlace?->id,
                'items' => $newItems,
            ]);
        });
    }

    /**
     * Translate fields with user friendly name.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'inventory' => __('inventory.inventory'),
            'from_place' => __('inventory.stock_transfer.props.from_place'),
            'to_place' => __('inventory.stock_transfer.props.to_place'),
            'date' => __('inventory.stock_transfer.props.date'),
            'description' => __('inventory.stock_transfer.props.description'),
            'items.*.item' => __('inventory.stock_item.stock_item'),
            'items.*.item.uuid' => __('inventory.stock_item.stock_item'),
            'items.*.quantity' => __('inventory.stock_transfer.props.quantity'),
            'items.*.description' => __('inventory.stock_transfer.props.description'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }
}
