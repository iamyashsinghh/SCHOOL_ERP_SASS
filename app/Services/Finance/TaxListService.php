<?php

namespace App\Services\Finance;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\TaxResource;
use App\Models\Tenant\Finance\Tax;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'name'];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('finance.tax.props.name'),
                'print_label' => 'name',
                'print_sub_label' => 'code',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'rate',
                'label' => trans('finance.tax.props.rate'),
                'print_label' => 'rate.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'components',
                'label' => trans('finance.tax.props.components'),
                'type' => 'array',
                'print_label' => 'components',
                'print_key' => 'code',
                'print_sub_key' => 'rate.formatted',
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
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        return Tax::query()
            ->byTeam()
            ->filter([
                'App\QueryFilters\LikeMatch:name',
                'App\QueryFilters\LikeMatch:code',
                'App\QueryFilters\ExactMatch:rate',
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return TaxResource::collection($this->filter($request)
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
}
