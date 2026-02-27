<?php

namespace App\Services\Finance;

use App\Http\Resources\Finance\FeeHeadResource;
use App\Http\Resources\Finance\TaxResource;
use App\Models\Finance\FeeComponent;
use App\Models\Finance\FeeHead;
use App\Models\Finance\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeeComponentService
{
    public function preRequisite(Request $request)
    {
        $feeHeads = FeeHeadResource::collection(FeeHead::query()
            ->byPeriod()
            ->get());

        $taxes = TaxResource::collection(Tax::query()
            ->byTeam()
            ->get());

        return compact('feeHeads', 'taxes');
    }

    public function findByUuidOrFail(string $uuid): FeeComponent
    {
        return FeeComponent::query()
            ->byPeriod()
            ->findByUuidOrFail($uuid, trans('finance.fee_component.fee_component'), 'message');
    }

    private function validateFeeInstallment(Request $request)
    {
        $feeInstallmentExists = \DB::table('fee_installment_records')
            ->whereFeeHeadId($request->fee_head_id)
            ->exists();

        if ($feeInstallmentExists) {
            throw ValidationException::withMessages(['message' => trans('finance.fee_head.could_not_modify_if_installment_exists')]);
        }
    }

    public function create(Request $request): FeeComponent
    {
        $this->validateFeeInstallment($request);

        \DB::beginTransaction();

        $feeComponent = FeeComponent::forceCreate($this->formatParams($request));

        \DB::commit();

        return $feeComponent;
    }

    private function formatParams(Request $request, ?FeeComponent $feeComponent = null): array
    {
        $formatted = [
            'name' => $request->name,
            'tax_id' => $request->tax_id,
        ];

        if (! $feeComponent) {
            $formatted['fee_head_id'] = $request->fee_head_id;
        }

        $meta = $feeComponent?->meta ?? [];
        $meta['tax_type'] = $request->tax_type;
        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, FeeComponent $feeComponent): void
    {
        $this->validateFeeInstallment($request);

        \DB::beginTransaction();

        $feeComponent->forceFill($this->formatParams($request, $feeComponent))->save();

        \DB::commit();
    }

    public function deletable(FeeComponent $feeComponent): bool
    {
        $feeStructureExists = \DB::table('fee_structure_components')
            ->whereFeeComponentId($feeComponent->id)
            ->exists();

        if ($feeStructureExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_component.fee_component'), 'dependency' => trans('finance.fee_structure.fee_structure')])]);
        }

        return true;
    }
}
