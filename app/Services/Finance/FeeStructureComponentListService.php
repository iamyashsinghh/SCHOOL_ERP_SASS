<?php

namespace App\Services\Finance;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\FeeInstallmentRecordResource;
use App\Models\Tenant\Finance\FeeInstallmentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeeStructureComponentListService extends ListGenerator
{
    protected $allowedSorts = ['created_at'];

    protected $defaultSort = 'created_at';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'feeStructure',
                'label' => trans('finance.fee_structure.fee_structure'),
                'print_label' => 'installment.structure.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'feeInstallment',
                'label' => trans('finance.fee_structure.installment'),
                'print_label' => 'installment.title',
                'sortable' => false,
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
                'key' => 'feeComponents',
                'label' => trans('finance.fee_component.fee_component'),
                'type' => 'array',
                'print_label' => 'components',
                'print_key' => 'name',
                'print_sub_key' => 'amount.formatted',
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
        return FeeInstallmentRecord::query()
            ->with('installment.structure', 'head', 'components.component')
            ->byPeriod()
            ->has('components')
            ->filter([
                'App\QueryFilters\LikeMatch:name',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return FeeInstallmentRecordResource::collection($this->filter($request)
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
