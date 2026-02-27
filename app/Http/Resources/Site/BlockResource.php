<?php

namespace App\Http\Resources\Site;

use App\Concerns\HasStorage;
use App\Enums\Site\BlockType;
use App\Support\MarkdownParser;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class BlockResource extends JsonResource
{
    use HasStorage, MarkdownParser;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => BlockType::getDetail($this->type),
            'title' => $this->title,
            'sub_title' => $this->sub_title,
            'content' => $this->content,
            'content_html' => $this->parse($this->content),
            'menu' => MenuResource::make($this->whenLoaded('menu')),
            'url' => $this->getMeta('url'),
            'background_color' => $this->getMeta('background_color'),
            'text_color' => $this->getMeta('text_color'),
            'has_flipped_animation' => $this->has_flipped_animation,
            $this->mergeWhen($this->type == BlockType::ACCORDION, [
                'accordion_items' => $this->getMeta('accordion_items', []),
            ]),
            $this->mergeWhen($this->type == BlockType::STAT_COUNTER, [
                'max_items_per_row' => (int) $this->getMeta('max_items_per_row', 2),
                'stat_counter_items' => $this->getMeta('stat_counter_items', []),
            ]),
            $this->mergeWhen($this->type == BlockType::TESTIMONIAL, [
                'testimonial_items' => $this->getMeta('testimonial_items', []),
            ]),
            'assets' => [
                'cover' => $this->cover_image,
                'default_cover' => ! Arr::get($this->assets, 'cover') ? true : false,
            ],
            $this->mergeWhen($this->type == BlockType::SLIDER, [
                'slider_images' => collect($this->slider_images)->map(function ($item) {
                    $item['url'] = $this->getImageFile(visibility: 'public', path: Arr::get($item, 'path'), default: '/images/site/cover.webp');

                    return $item;
                })->toArray(),
                'default_slider_image' => $this->default_slider_image,
            ]),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
