<?php

namespace App\Services\Finance;

use App\Actions\Finance\CreateCustomFeeInstallment;
use App\Actions\Finance\CreateFeeInstallment;
use App\Enums\Finance\LateFeeFrequency;
use App\Http\Resources\Finance\FeeGroupResource;
use App\Http\Resources\Transport\FeeResource as TransportFeeResource;
use App\Models\Tenant\Finance\FeeGroup;
use App\Models\Tenant\Finance\FeeHead;
use App\Models\Tenant\Finance\FeeInstallment;
use App\Models\Tenant\Finance\FeeInstallmentRecord;
use App\Models\Tenant\Finance\FeeStructure;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Transport\Fee as TransportFee;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FeeStructureService
{
    public function preRequisite(): array
    {
        $transportFees = TransportFeeResource::collection(TransportFee::query()
            ->byPeriod()
            ->get());

        $feeGroups = FeeGroupResource::collection(FeeGroup::query()
            ->with('heads')
            ->byPeriod()
            ->get()
            ->filter(function ($feeGroup) {
                return ! $feeGroup->getMeta('is_custom');
            }));

        $frequencies = LateFeeFrequency::getOptions();

        return compact('transportFees', 'frequencies', 'feeGroups');
    }

    public function getOptionalFeeHeads(FeeStructure $feeStructure): array
    {
        $feeInstallmentRecords = FeeInstallmentRecord::query()
            ->with('head')
            ->whereHas('installment', function ($q) use ($feeStructure) {
                $q->whereFeeStructureId($feeStructure->id);
            })
            ->whereIsOptional(1)
            ->get();

        $optionalFeeHeads = $feeInstallmentRecords->map(function ($feeInstallmentRecord) {
            return [
                'name' => $feeInstallmentRecord->head->name,
                'uuid' => $feeInstallmentRecord->head->uuid,
            ];
        })->unique('uuid')->values()->all();

        return $optionalFeeHeads;
    }

    public function getComponentFeeHeads(FeeStructure $feeStructure): array
    {
        $feeHeads = FeeHead::query()
            ->has('components')
            ->get();

        $feeInstallmentRecords = FeeInstallmentRecord::query()
            ->with('installment', 'head')
            ->whereIn('fee_head_id', $feeHeads->pluck('id'))
            ->get();

        $componentFeeHeads = $feeInstallmentRecords->map(function ($feeInstallmentRecord) {
            return [
                'name' => $feeInstallmentRecord->head->name.' ('.$feeInstallmentRecord->installment->title.')',
                'uuid' => $feeInstallmentRecord->uuid,
            ];
        })->all();

        return $componentFeeHeads;
    }

    public function create(Request $request): FeeStructure
    {
        \DB::beginTransaction();

        $feeStructure = FeeStructure::forceCreate($this->formatParams($request));

        $this->updateInstallments($request, $feeStructure);

        \DB::commit();

        return $feeStructure;
    }

    private function formatParams(Request $request, ?FeeStructure $feeStructure = null): array
    {
        $formatted = [
            'name' => $request->name,
            'description' => $request->description,
        ];

        if (! $feeStructure) {
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        return $formatted;
    }

    private function updateInstallments(Request $request, FeeStructure $feeStructure, $action = 'create'): void
    {
        $feeInstallmentUuids = [];

        foreach ($request->fee_groups as $feeGroup) {
            foreach (Arr::get($feeGroup, 'installments', []) as $params) {
                $params['action'] = $action;
                $params['fee_group_id'] = Arr::get($feeGroup, 'id');

                $feeInstallment = (new CreateFeeInstallment)->execute(feeStructure: $feeStructure, params: $params);
                $feeInstallmentUuids[] = $feeInstallment->uuid;
            }
        }

        $customFeeInstallment = (new CreateCustomFeeInstallment)->execute($feeStructure->id);
        if ($customFeeInstallment) {
            $feeInstallmentUuids[] = $customFeeInstallment->uuid;
        }

        FeeInstallment::whereFeeStructureId($feeStructure->id)->whereNotIn('uuid', $feeInstallmentUuids)->delete();
    }

    public function update(Request $request, FeeStructure $feeStructure): void
    {
        // $feeAllocationExists = \DB::table('fee_allocations')
        //     ->whereFeeStructureId($feeStructure->id)
        //     ->exists();

        // if ($feeAllocationExists) {
        //     throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_structure.fee_structure'), 'dependency' => trans('finance.fee_structure.allocation')])]);
        // }

        if (Student::query()->whereFeeStructureId($feeStructure->id)->exists()) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_structure.fee_structure'), 'dependency' => trans('student.student')])]);
        }

        \DB::beginTransaction();

        $feeStructure->forceFill($this->formatParams($request, $feeStructure))->save();

        $this->updateInstallments($request, $feeStructure, 'update');

        \DB::commit();
    }

    public function deletable(FeeStructure $feeStructure, $validate = false): ?bool
    {
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
