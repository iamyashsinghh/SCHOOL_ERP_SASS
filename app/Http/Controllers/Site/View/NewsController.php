<?php

namespace App\Http\Controllers\Site\View;

use App\Enums\News\Status;
use App\Http\Controllers\Controller;
use App\Models\Tenant\News\News;
use App\Models\Tenant\Site\Menu;
use App\Support\MarkdownParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    use MarkdownParser;

    public function __invoke(Request $request, string $slug, string $news)
    {
        $isUuid = Str::isUuid($news);

        $news = News::query()
            ->with('category', 'tags')
            ->when($isUuid, function ($query) use ($news) {
                $query->whereUuid($news);
            }, function ($query) use ($news) {
                $query->whereSlug($news);
            })
            ->where('status', Status::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->toDateTimeString())
            ->firstOrFail();

        $news->content = $this->parse($news->content);

        $relatedCategoryNews = News::query()
            ->with('category', 'tags')
            ->where('id', '!=', $news->id)
            ->where('category_id', $news->category_id)
            ->where('status', Status::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->toDateTimeString())
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get();

        $relatedTagsNews = News::query()
            ->with('category', 'tags')
            ->where('id', '!=', $news->id)
            ->where('status', Status::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->toDateTimeString())
            ->whereHas('tags', function ($query) use ($news) {
                $query->whereIn('tags.id', $news->tags->pluck('id')->all());
            })
            ->orderBy('published_at', 'desc')
            ->first();

        $menu = Menu::query()
            ->whereSlug($slug)
            ->first();

        return view(config('config.site.view').'news', compact('news', 'relatedCategoryNews', 'relatedTagsNews', 'slug', 'menu'));
    }
}
