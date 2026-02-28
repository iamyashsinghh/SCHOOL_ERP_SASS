<?php

namespace App\Services\Site;

use App\Enums\Site\BlockType;
use App\Http\Resources\Site\MenuResource;
use App\Models\Tenant\Site\Block;
use App\Models\Tenant\Site\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlockService
{
    public function preRequisite(Request $request): array
    {
        $menus = MenuResource::collection(Menu::query()->get());

        $types = BlockType::getOptions();

        return compact('menus', 'types');
    }

    public function create(Request $request): Block
    {
        \DB::beginTransaction();

        $block = Block::forceCreate($this->formatParams($request));

        \DB::commit();

        return $block;
    }

    private function formatParams(Request $request, ?Block $block = null): array
    {
        $formatted = [
            'name' => Str::upper($request->name),
            'type' => $request->type,
            'title' => $request->title,
            'sub_title' => $request->sub_title,
            'content' => $request->content,
            'menu_id' => $request->menu_id,
        ];

        $meta = $block?->meta ?? [];
        $meta['url'] = $request->url;
        $meta['background_color'] = $request->background_color;
        $meta['text_color'] = $request->text_color;
        $meta['has_flipped_animation'] = $request->boolean('has_flipped_animation');
        $meta['accordion_items'] = $request->type == 'accordion' ? collect($request->input('accordion_items', []))->map(function ($item) {
            return [
                'heading' => $item['heading'],
                'description' => $item['description'],
            ];
        }) : [];
        $meta['max_items_per_row'] = $request->type == 'stat_counter' ? (int) $request->input('max_items_per_row', 2) : null;
        $meta['stat_counter_items'] = $request->type == 'stat_counter' ? collect($request->input('stat_counter_items', []))->map(function ($item) {
            return [
                'heading' => $item['heading'],
                'count' => $item['count'],
            ];
        }) : [];
        $meta['testimonial_items'] = $request->type == 'testimonial' ? collect($request->input('testimonial_items', []))->map(function ($item) {
            return [
                'name' => $item['name'],
                'detail' => $item['detail'],
                'comment' => $item['comment'],
            ];
        }) : [];

        $formatted['meta'] = $meta;

        if (! $block) {
            //
        }

        return $formatted;
    }

    public function update(Request $request, Block $block): void
    {
        \DB::beginTransaction();

        $block->forceFill($this->formatParams($request, $block))->save();

        \DB::commit();
    }

    public function deletable(Block $block): void {}
}
