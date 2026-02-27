<?php

namespace App\Services\Finance;

use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInstallmentRecord;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\FeeStructureComponent;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FeeStructureComponentService
{
    public function preRequisite(): array
    {
        $feeHeads = FeeHead::query()
            ->byPeriod()
            ->with('components.tax')
            ->has('components')
            ->get();

        $feeStructures = FeeStructure::query()
            ->select('id', 'uuid', 'name')
            ->byPeriod()
            ->get();

        $feeInstallmentRecords = FeeInstallmentRecord::query()
            ->select('fee_installment_records.uuid', 'fee_installment_records.fee_head_id', 'fee_installments.fee_structure_id', 'fee_installments.fee_structure_id', 'fee_installments.title', 'fee_installment_records.amount')
            ->join('fee_installments', 'fee_installment_records.fee_installment_id', '=', 'fee_installments.id')
            ->whereIn('fee_head_id', $feeHeads->pluck('id'))
            ->whereIn('fee_structure_id', $feeStructures->pluck('id'))
            ->get();

        foreach ($feeStructures as $feeStructure) {
            foreach ($feeHeads as $feeHead) {
                $feeInstallmentHeads = $feeInstallmentRecords->where('fee_head_id', $feeHead->id)->where('fee_structure_id', $feeStructure->id);

                if ($feeInstallmentHeads->count() > 0) {
                    $feeStructure->heads = $feeInstallmentHeads->filter(function ($feeInstallmentHead) {
                        return $feeInstallmentHead->amount->value > 0;
                    })->map(function ($feeInstallmentHead) use ($feeHeads) {
                        $feeHead = $feeHeads->where('id', $feeInstallmentHead->fee_head_id)->first();

                        return [
                            'uuid' => $feeInstallmentHead->uuid,
                            'name' => $feeHead->name.' - '.$feeInstallmentHead->title,
                            'amount' => $feeInstallmentHead->amount,
                            'components' => $feeHead->components->map(function ($component) {
                                return [
                                    'uuid' => $component->uuid,
                                    'name' => $component->name,
                                    'tax' => $component->tax?->code_with_rate,
                                    'tax_type' => $component->getMeta('tax_type', 'inclusive'),
                                    'hsn_code' => $component->getMeta('hsn_code'),
                                ];
                            }),
                        ];
                    })->values();
                }
            }
        }

        return compact('feeStructures');
    }

    public function findByUuidOrFail(string $uuid): FeeInstallmentRecord
    {
        return FeeInstallmentRecord::query()
            ->with('installment.structure', 'head', 'components.component')
            ->byPeriod()
            ->has('components')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(Request $request): FeeStructureComponent
    {
        if (Student::query()->whereFeeStructureId($request->fee_structure_id)->exists()) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_structure.fee_structure'), 'dependency' => trans('student.student')])]);
        }

        $existingComponents = FeeStructureComponent::query()
            ->where('fee_installment_record_id', $request->fee_installment_record_id)
            ->whereIn('fee_component_id', Arr::pluck($request->components, 'id'))
            ->get();

        if ($existingComponents->count() > 0) {
            throw ValidationException::withMessages(['message' => trans('finance.fee_structure.component_already_exists')]);
        }

        \DB::beginTransaction();

        foreach ($request->components as $component) {
            $feeStructureComponent = FeeStructureComponent::forceCreate([
                'fee_installment_record_id' => $request->fee_installment_record_id,
                'fee_component_id' => Arr::get($component, 'id'),
                'amount' => Arr::get($component, 'amount', 0),
            ]);
        }

        \DB::commit();

        return $feeStructureComponent;
    }

    public function update(Request $request, FeeInstallmentRecord $feeInstallmentRecord): void
    {
        $feeStructure = $feeInstallmentRecord->installment->structure;

        if (Student::query()->whereFeeStructureId($feeStructure->id)->exists()) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_structure.fee_structure'), 'dependency' => trans('student.student')])]);
        }

        \DB::beginTransaction();

        foreach ($request->components as $component) {
            $feeStructureComponent = FeeStructureComponent::firstOrCreate([
                'fee_installment_record_id' => $request->fee_installment_record_id,
                'fee_component_id' => Arr::get($component, 'id'),
            ]);

            $feeStructureComponent->forceFill([
                'amount' => Arr::get($component, 'amount', 0),
            ])->save();
        }

        \DB::commit();
    }

    public function deletable(FeeInstallmentRecord $feeInstallmentRecord, $validate = false): ?bool
    {
        $feeStructure = $feeInstallmentRecord->installment->structure;

        $feeAllocationExists = \DB::table('fee_allocations')
            ->whereFeeStructureId($feeStructure->id)
            ->exists();

        if ($feeAllocationExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_structure.fee_structure'), 'dependency' => trans('finance.fee_structure.allocation')])]);
        }

        $studentExists = \DB::table('students')
            ->whereFeeStructureId($feeStructure->id)
            ->exists();

        if ($studentExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_structure.fee_structure'), 'dependency' => trans('student.student')])]);
        }

        return true;
    }
}
