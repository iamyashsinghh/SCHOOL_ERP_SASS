<?php

namespace App\Services;

use App\Enums\OptionType;
use App\Helpers\ListHelper;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OptionService
{
    public function preRequisite(Request $request): array
    {
        $colors = ListHelper::getListKey('colors');

        $option = OptionType::tryFrom($request->type);
        $optionDetail = $option?->detail();
        $hasColor = Arr::get($optionDetail, 'has_color', true);

        return compact('colors', 'hasColor');
    }

    public function create(Request $request): Option
    {
        \DB::beginTransaction();

        $optionPosition = Option::query()
            ->byTeam()
            ->whereType($request->type)
            ->count();

        $data = $this->formatParams($request);
        $data['meta']['position'] = $optionPosition + 1;

        $option = Option::forceCreate($data);

        \DB::commit();

        return $option;
    }

    private function formatParams(Request $request, ?Option $option = null): array
    {
        $formatted = [
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => $request->type,
            'description' => $request->description,
            'meta' => $request->has('details') ? $request->safe()?->details : [],
        ];

        $formatted['meta']['color'] = $request->color ?? '';

        if (! $option && $request->team) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Option $option): void
    {
        \DB::beginTransaction();

        $option->forceFill($this->formatParams($request, $option))->save();

        \DB::commit();
    }

    public function deletable(Option $option): void {}
}
