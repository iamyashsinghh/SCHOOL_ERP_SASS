<?php

namespace App\Services\Finance;

use App\Enums\Finance\DefaultCustomFeeType;
use App\Http\Resources\Finance\FeeGroupResource;
use App\Http\Resources\Finance\TaxResource;
use App\Models\Finance\FeeComponent;
use App\Models\Finance\FeeGroup;
use App\Models\Finance\FeeHead;
use App\Models\Finance\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FeeHeadService
{
    public function preRequisite(Request $request)
    {
        $feeGroups = FeeGroupResource::collection(FeeGroup::query()
            ->byPeriod()
            ->get());

        $taxes = TaxResource::collection(Tax::query()
            ->byTeam()
            ->get());

        $types = DefaultCustomFeeType::getOptions();

        return compact('feeGroups', 'taxes', 'types');
    }

    public function findByUuidOrFail(string $uuid): FeeHead
    {
        return FeeHead::query()
            ->byPeriod()
            ->findByUuidOrFail($uuid, trans('finance.fee_head.fee_head'), 'message');
    }

    public function create(Request $request): FeeHead
    {
        \DB::beginTransaction();

        $feeHead = FeeHead::forceCreate($this->formatParams($request));

        if ($request->has_components) {
            $this->updateComponents($request, $feeHead);
        }

        \DB::commit();

        return $feeHead;
    }

    private function formatParams(Request $request, ?FeeHead $feeHead = null): array
    {
        $formatted = [
            'name' => $request->name,
            'code' => $request->code,
            'shortcode' => $request->shortcode,
            'fee_group_id' => $request->fee_group_id,
            'type' => $request->type,
            'tax_id' => $request->tax_id,
            'is_tax_applicable' => $request->tax_id ? true : false,
            'description' => $request->description,
        ];

        if (! $feeHead) {
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        $meta = $feeHead?->meta ?? [];

        $meta['tax_type'] = $request->tax_type;
        $meta['hsn_code'] = $request->hsn_code;

        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function updateComponents(Request $request, FeeHead $feeHead): void
    {
        $components = $request->has_components ? $request->components : [];

        $names = [];

        foreach ($components as $component) {
            $names[] = Arr::get($component, 'name');

            $feeComponent = FeeComponent::firstOrCreate([
                'fee_head_id' => $feeHead->id,
                'name' => Arr::get($component, 'name'),
            ]);

            $feeComponent->tax_id = Arr::get($component, 'tax_id');
            $feeComponent->setMeta([
                'tax_type' => Arr::get($component, 'tax_type'),
                'hsn_code' => Arr::get($component, 'hsn_code'),
            ]);
            $feeComponent->save();
        }

        FeeComponent::query()
            ->whereFeeHeadId($feeHead->id)
            ->whereNotIn('name', $names)
            ->delete();
    }

    public function update(Request $request, FeeHead $feeHead): void
    {
        $feeInstallmentExists = \DB::table('fee_installment_records')
            ->whereFeeHeadId($feeHead->id)
            ->exists();

        // disable editing if fee installment exists
        // if ($feeInstallmentExists && $feeHead?->fee_group_id != $request->fee_group_id) {
        if ($feeInstallmentExists) {
            throw ValidationException::withMessages(['message' => trans('finance.fee_head.could_not_modify_if_installment_exists')]);
        }

        if (! $feeHead->fee_group_id && $request->fee_group_id) {
            $feeInstallmentExists = \DB::table('fee_installments')
                ->whereFeeGroupId($request->fee_group_id)
                ->exists();

            if ($feeInstallmentExists) {
                throw ValidationException::withMessages(['message' => trans('finance.fee_head.could_not_modify_if_installment_exists')]);
            }
        }

        \DB::beginTransaction();

        $feeHead->forceFill($this->formatParams($request, $feeHead))->save();

        $this->updateComponents($request, $feeHead);

        \DB::commit();
    }

    public function deletable(FeeHead $feeHead): bool
    {
        $feeConcessionExists = \DB::table('fee_concession_records')
            ->whereFeeHeadId($feeHead->id)
            ->exists();

        if ($feeConcessionExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_head.fee_head'), 'dependency' => trans('finance.fee_concession.fee_concession')])]);
        }

        $feeInstallmentExists = \DB::table('fee_installment_records')
            ->whereFeeHeadId($feeHead->id)
            ->exists();

        if ($feeInstallmentExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_head.fee_head'), 'dependency' => trans('finance.fee_structure.installment')])]);
        }

        return true;
    }
}
