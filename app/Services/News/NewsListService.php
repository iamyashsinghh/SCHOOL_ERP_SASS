<?php

namespace App\Services\News;

use App\Contracts\ListGenerator;
use App\Http\Resources\News\NewsSummaryResource;
use App\Models\News\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class NewsListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'title', 'published_at'];

    protected $defaultSort = 'created_at';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'title',
                'label' => trans('news.props.title'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('news.category.category'),
                'print_label' => 'category.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'publishedAt',
                'label' => trans('news.props.published_at'),
                'print_label' => 'published_at.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'isPublished',
                'label' => '',
                'print_label' => 'is_published',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'tag',
                'label' => trans('general.tag'),
                'print_label' => 'tags_display',
                'printable' => false,
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
            array_unshift($headers, ['key' => 'selectAll', 'sortable' => false]);
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $newsCategories = Str::toArray($request->query('categories'));
        $tagsIncluded = Str::toArray($request->query('tags_included'));
        $tagsExcluded = Str::toArray($request->query('tags_excluded'));

        return News::query()
            ->with('category', 'tags')
            ->when($tagsIncluded, function ($q, $tagsIncluded) {
                $q->whereHas('tags', function ($q) use ($tagsIncluded) {
                    $q->whereIn('name', $tagsIncluded);
                });
            })
            ->when($tagsExcluded, function ($q, $tagsExcluded) {
                $q->whereDoesntHave('tags', function ($q) use ($tagsExcluded) {
                    $q->whereIn('name', $tagsExcluded);
                });
            })
            ->when($newsCategories, function ($q, $newsCategories) {
                $q->whereHas('category', function ($q) use ($newsCategories) {
                    $q->whereIn('uuid', $newsCategories);
                });
            })
            ->when($request->boolean('is_archived'), function ($q) {
                $q->whereNotNull('archived_at');
            }, function ($q) {
                $q->whereNull('archived_at');
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\LikeMatch:search,title,sub_title',
                'App\QueryFilters\DateBetween:start_date,end_date,published_at,datetime',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return NewsSummaryResource::collection($this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }

    public function getIds(Request $request): array
    {
        return $this->filter($request)->select('uuid')->get()->pluck('uuid')->all();
    }
}
