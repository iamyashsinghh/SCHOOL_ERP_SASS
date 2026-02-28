<?php

namespace App\Services;

use App\Concerns\HasStorage;
use App\Enums\Site\BlockType;
use App\Models\Tenant\Site\Block;
use App\Models\Tenant\Site\Menu;
use App\Models\Tenant\Site\Page;
use App\Support\MarkdownParser;
use DOMDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SiteService
{
    use HasStorage, MarkdownParser;

    public function getPage(?string $slug = '')
    {
        $slug = $slug ?? 'Home';

        $menu = Menu::query()
            ->whereSlug($slug)
            ->first();

        if ($slug == 'Home' && ! $menu) {
            return redirect()->route('app');
        }

        if (! $menu) {
            abort(404);
        }

        if (! $menu->page_id) {
            return redirect()->route('app');
        }

        $page = $menu->page;

        $navs = [];

        if ($menu->parent_id) {
            array_push($navs, [
                'name' => $menu->parent->name,
                'url' => route('site.page', ['slug' => $menu->parent->slug]),
            ]);
        }

        array_push($navs, [
            'name' => $menu->name,
            'url' => route('site.page', ['slug' => $menu->slug]),
        ]);

        $metaTitle = Arr::get($page->seo, 'meta_title');
        $metaDescription = Arr::get($page->seo, 'meta_description');
        $metaKeywords = Arr::get($page->seo, 'meta_keywords');

        $address = Arr::toAddress([
            'address_line1' => config('config.general.app_address_line1'),
            'address_line2' => config('config.general.app_address_line2'),
            'city' => config('config.general.app_city'),
            'state' => config('config.general.app_state'),
            'zipcode' => config('config.general.app_zipcode'),
            'country' => config('config.general.app_country'),
        ]);

        config([
            'config.general.app_address' => $address,
        ]);

        $content = $page->content;

        $content = $this->parse($content, ['skip_embedded_links' => true]);

        $content = preg_replace('/<p>(#CONTAINER#)<\/p>(.*?)<p>(#CONTAINER#)<\/p>/s', '<div style="margin: 40px 0;"><div class="flex-col flex md:flex-row gap-2">$2</div></div>', $content);

        $content = preg_replace('/<p>(#SECTION#)<\/p>(.*?)<p>(#SECTION#)<\/p>/s', '<div style="margin: 40px 0;">$2</div>', $content);

        $parts = $this->getParts($content);

        $blockNames = collect($parts)->where('type', 'array')->pluck('content')->flatten()->toArray();

        $blocks = Block::query()
            ->with('menu')
            ->whereIn('name', $blockNames)
            ->where(function ($q) {
                $q->whereNull('type')
                    ->orWhere('type', '!=', 'slider');
            })
            ->orderBy('position', 'asc')
            ->get();

        $blocks = $blocks->map(function ($block) {
            $block->url = $block->getMeta('url');
            $block->target_url = '_blank';

            $block->has_cover = Arr::get($block->assets, 'cover') ? true : false;

            if ($block->menu_id) {
                $block->target_url = '_self';
                $block->url = route('site.page', ['slug' => $block->menu->slug]);
            }

            return $block;
        });

        $hasSlider = $page->getMeta('has_slider', false);

        $sliderImages = [];
        if ($hasSlider) {
            $page->has_slider = true;
            $sliderUuid = $page->getMeta('slider');

            $slider = Block::query()
                ->whereUuid($sliderUuid)
                ->where('type', BlockType::SLIDER)
                ->first();

            $sliderImages = collect($slider?->slider_images ?? [])->map(function ($item) {
                $item['url'] = $this->getImageFile(visibility: 'public', path: Arr::get($item, 'path'), default: '/images/site/cover.webp');

                return $item;
            })->toArray();
        } else {
            $page->has_slider = false;
        }

        return view(config('config.site.view').'page', compact('menu', 'page', 'parts', 'metaTitle', 'metaDescription', 'metaKeywords', 'blocks', 'sliderImages', 'navs'));
    }

    public function getPageView(string $slug)
    {
        $page = Page::query()
            ->where('seo->is_public', true)
            ->where('seo->slug', $slug)
            ->firstOrFail();

        $content = $page->content;

        $content = $this->parse($content);

        return $content;
    }

    private function getTopElements(?string $content = null): array
    {
        if (! $content) {
            return [];
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);

        // Use body wrapper to handle fragments properly
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$content.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body) {
            return [];
        }

        $topElements = [];
        foreach ($body->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $topElements[] = $dom->saveHTML($child);
            }
        }

        return $topElements;
    }

    private function getParts($content)
    {
        // first remove all new line characters
        $content = str_replace("\n", '', $content);

        // Split content at <p>## markers i.e. blocks
        $parts = preg_split('/<p>##/', $content);

        $contentParts = [];

        // Handle the first part (everything before first ##)
        if (! empty($parts[0])) {
            $contentParts = $this->getContents($parts[0], $contentParts);
        }

        // Handle remaining parts
        for ($i = 1; $i < count($parts); $i++) {
            if (empty($parts[$i])) {
                continue;
            }

            // Split at </p> to separate block content from regular content
            $subParts = explode('</p>', $parts[$i], 2);

            // Extract block names
            preg_match_all('/##([^#]+)##/', '<p>##'.$subParts[0], $matches);
            if (! empty($matches[1])) {
                $contentParts[] = [
                    'type' => 'array',
                    'content' => $matches[1],
                ];
            }

            // Add remaining content as HTML if it exists
            if (! empty($subParts[1])) {
                $contentParts = $this->getContents(trim($subParts[1]), $contentParts);
            }
        }

        return $contentParts;
    }

    private function getLinkProvider(string $url): ?string
    {
        $uri = uri($url);

        $host = strtolower($uri->host());
        $host = preg_replace('/^www\./', '', $host);

        return match ($host) {
            'youtube.com', 'youtu.be' => 'youtube',
            'x.com', 'twitter.com' => 'twitter',
            'facebook.com', 'fb.watch' => 'facebook',
            default => null,
        };
    }

    private function getContents(string $content, array &$contentParts): array
    {
        $topElements = $this->getTopElements($content);

        foreach ($topElements as $element) {
            if (preg_match_all('~<p>\s*(https?://[^\s<]+)\s*</p>~i', $element, $linkMatches)) {
                foreach ($linkMatches[1] ?? [] as $link) {
                    $provider = $this->getLinkProvider($link);

                    if ($provider == 'youtube') {
                        $link = $this->getYouTubeVideoId($link);
                    }

                    $contentParts[] = [
                        'type' => $provider,
                        'content' => $link,
                    ];
                }
            } else {
                $contentParts[] = [
                    'type' => 'html',
                    'content' => trim($element),
                ];
            }
        }

        return $contentParts;
    }

    private function getYouTubeVideoId(string $url): ?string
    {
        $uri = uri($url);

        $host = strtolower(preg_replace('/^www\./', '', $uri->host()));

        return match ($host) {
            'youtube.com' => Str::after($uri->query('v'), 'v='),
            'youtu.be' => ltrim($uri->path(), '/'),
            default => null,
        };
    }
}
