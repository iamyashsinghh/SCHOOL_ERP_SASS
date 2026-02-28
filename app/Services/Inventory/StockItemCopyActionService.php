<?php

namespace App\Services\Inventory;

use App\Actions\CreateTag;
use App\Enums\Inventory\HoldStatus;
use App\Enums\OptionType;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Inventory\StockItemCopy;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class StockItemCopyActionService
{
    public function preRequisite(Request $request, StockItemCopy $stockItemCopy)
    {
        $conditions = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::STOCK_ITEM_CONDITION)
            ->get());

        return compact('conditions');
    }

    private function getStockItemCopyStatus(Collection $stockItemCopies)
    {
        return StockItemCopy::query()
            ->whereHas('item', function ($query) {
                $query->byTeam();
            })
            ->whereIn('stock_item_copies.uuid', $stockItemCopies->pluck('uuid'))
            ->select('stock_item_copies.uuid', 'stock_item_copies.id', 'stock_item_copies.hold_status')
            ->get();
    }

    public function updateBulkCondition(Request $request)
    {
        $request->validate([
            'stock_item_copies' => 'array',
            'condition' => 'required|uuid',
        ]);

        $condition = Option::query()
            ->byTeam()
            ->where('type', OptionType::STOCK_ITEM_CONDITION)
            ->whereUuid($request->input('condition'))
            ->first();

        if (! $condition) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $stockItemCopies = StockItemCopy::query()
            ->whereIn('uuid', $request->input('stock_item_copies', []))
            ->get();

        $updateCount = 0;
        foreach ($stockItemCopies as $stockItemCopy) {
            $stockItemCopy->condition_id = $condition->id;
            $stockItemCopy->save();
            $updateCount++;
        }

        return $updateCount;
    }

    public function updateBulkStatus(Request $request)
    {
        $request->validate([
            'stock_item_copies' => 'array',
            'status' => 'required|in:hold,stock',
            'hold_status' => ['required_if:status,hold', new Enum(HoldStatus::class)],
        ]);

        $stockItemCopies = StockItemCopy::query()
            ->whereIn('uuid', $request->input('stock_item_copies', []))
            // let them do whatever they want
            // ->when($request->status == 'stock', function ($query) use ($request) {
            //     $query->where('status', '!=', 'hold');
            // })
            ->get();

        $updateCount = 0;
        foreach ($stockItemCopies as $stockItemCopy) {
            if ($request->status == 'hold') {
                $stockItemCopy->hold_status = $request->hold_status;
            } else {
                $stockItemCopy->hold_status = null;
            }
            $stockItemCopy->save();
            $updateCount++;
        }

        return $updateCount;
    }

    public function updateBulkTags(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:assign,remove',
            'stock_item_copies' => 'array',
            'tags' => 'array',
            'tags.*' => 'required|string|distinct',
        ]);

        $stockItemCopies = StockItemCopy::query()
            ->whereIn('uuid', $request->input('stock_item_copies', []))
            ->get();

        $tags = (new CreateTag)->execute($request->input('tags', []));

        if ($request->input('action') === 'assign') {
            foreach ($stockItemCopies as $stockItemCopy) {
                $stockItemCopy->tags()
                    ->sync($tags);
            }
        } else {
            foreach ($stockItemCopies as $stockItemCopy) {
                $stockItemCopy->tags()
                    ->detach($tags);
            }
        }
    }
}
