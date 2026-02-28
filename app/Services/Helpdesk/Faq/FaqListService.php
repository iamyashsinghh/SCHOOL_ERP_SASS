<?php

namespace App\Services\Helpdesk\Faq;

use App\Contracts\ListGenerator;
use App\Http\Resources\Helpdesk\Faq\FaqResource;
use App\Models\Tenant\Helpdesk\Faq\Faq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FaqListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'title'];

    protected $defaultSort = 'created_at';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'question',
                'label' => trans('helpdesk.faq.props.question'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('helpdesk.faq.category.category'),
                'print_label' => 'category.name',
                'sortable' => false,
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
            [
                'key' => 'createdAt',
                'label' => trans('general.created_at'),
                'print_label' => 'created_at.formatted',
                'sortable' => true,
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
        $faqCategories = Str::toArray($request->query('categories'));
        $tagsIncluded = Str::toArray($request->query('tags_included'));
        $tagsExcluded = Str::toArray($request->query('tags_excluded'));

        return Faq::query()
            ->byTeam()
            ->whereNull('model_type')
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
            ->when($faqCategories, function ($q, $faqCategories) {
                $q->whereHas('category', function ($q) use ($faqCategories) {
                    $q->whereIn('uuid', $faqCategories);
                });
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\LikeMatch:search,question,answer',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return FaqResource::collection($this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->when($request->query('preview'), function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            }))
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
