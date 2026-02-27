<?php

namespace App\Services\Finance;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\FeeHeadResource;
use App\Models\Finance\FeeHead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FeeHeadListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'name'];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('finance.fee_head.props.name'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'feeGroup',
                'label' => trans('finance.fee_group.fee_group'),
                'print_label' => 'group.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'code',
                'label' => trans('finance.fee_head.props.code'),
                'print_label' => 'code',
                'print_sub_label' => 'shortcode',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'tax',
                'label' => trans('finance.tax.tax'),
                'print_label' => 'tax.code_with_rate',
                'print_sub_label' => 'tax.tax_type.label',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'components',
                'label' => trans('finance.fee_head.props.components'),
                'type' => 'array',
                'print_label' => 'components',
                'print_key' => 'name',
                'print_sub_key' => 'tax.code_with_rate',
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
        $feeGroups = Str::toArray($request->query('fee_groups'));

        return FeeHead::query()
            ->with('group', 'tax', 'components.tax')
            ->byPeriod()
            ->when($feeGroups, function ($q, $feeGroups) {
                $q->whereHas('group', function ($q) use ($feeGroups) {
                    $q->whereIn('uuid', $feeGroups);
                });
            })
            ->filter([
                'App\QueryFilters\LikeMatch:name',
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return FeeHeadResource::collection($this->filter($request)
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
