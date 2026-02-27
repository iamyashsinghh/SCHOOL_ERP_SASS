<?php

namespace App\Services;

use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OptionActionService
{
    public function reorder(Request $request): void
    {
        $items = $request->items ?? [];

        $allItems = Option::query()
            ->when($request->team, function ($q) {
                $q->byTeam();
            })
            ->whereType($request->type)
            ->get();

        foreach ($items as $index => $item) {
            $option = $allItems->firstWhere('uuid', Arr::get($item, 'uuid'));

            if (! $option) {
                continue;
            }

            $option->position = $index + 1;
            $option->save();
        }

        // previous implementation not sure where it was used
        // $request->validate(['uuids' => 'array|min:1']);

        // foreach ($request->uuids as $order => $uuid) {
        //     Option::query()
        //         ->when($request->team, function ($q) {
        //             $q->byTeam();
        //         })
        //         ->whereType($request->query('type'))
        //         ->whereUuid($uuid)->update(['meta->position' => $order]);
        // }
    }
}
