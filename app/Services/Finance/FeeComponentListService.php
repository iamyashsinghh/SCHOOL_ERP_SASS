<?php

namespace App\Services\Finance;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\FeeComponentResource;
use App\Models\Finance\FeeComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FeeComponentListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'name'];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('finance.fee_component.props.name'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'feeHead',
                'label' => trans('finance.fee_head.fee_head'),
                'print_label' => 'head.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'tax',
                'label' => trans('finance.tax.tax'),
                'print_label' => 'tax.code_with_rate',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'hsnCode',
                'label' => trans('finance.tax.props.hsn_code'),
                'print_label' => 'hsn_code',
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
        $feeHeads = Str::toArray($request->query('fee_heads'));

        return FeeComponent::query()
            ->with('head', 'tax')
            ->byPeriod()
            ->when($feeHeads, function ($q, $feeHeads) {
                $q->whereHas('head', function ($q) use ($feeHeads) {
                    $q->whereIn('uuid', $feeHeads);
                });
            })
            ->filter([
                'App\QueryFilters\LikeMatch:name',
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return FeeComponentResource::collection($this->filter($request)
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
