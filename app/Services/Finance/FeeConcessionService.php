<?php

namespace App\Services\Finance;

use App\Actions\Student\UpdateStudentFeeConcession;
use App\Http\Resources\Finance\FeeHeadResource;
use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Finance\FeeConcessionRecord;
use App\Models\Tenant\Finance\FeeHead;
use App\Models\Tenant\Student\Fee;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FeeConcessionService
{
    public function preRequisite(): array
    {
        $heads = FeeHeadResource::collection(FeeHead::query()
            ->byPeriod()
            ->get());

        $types = [
            ['label' => trans('finance.fee_concession.props.percent'), 'value' => 'percent'],
            ['label' => trans('finance.fee_concession.props.amount'), 'value' => 'amount'],
        ];

        return compact('heads', 'types');
    }

    public function findByUuidOrFail(string $uuid): FeeConcession
    {
        return FeeConcession::query()
            ->byPeriod()
            ->findByUuidOrFail($uuid, trans('finance.fee_concession.fee_concession'), 'message');
    }

    public function create(Request $request): FeeConcession
    {
        \DB::beginTransaction();

        $feeConcession = FeeConcession::forceCreate($this->formatParams($request));

        $this->updateRecords($request, $feeConcession);

        \DB::commit();

        return $feeConcession;
    }

    private function formatParams(Request $request, ?FeeConcession $feeConcession = null): array
    {
        $formatted = [
            'name' => $request->name,
            'transport' => [
                'type' => $request->transport_type,
                'value' => $request->transport_value,
                'secondary_type' => $request->transport_secondary_type ?? 'percent',
                'secondary_value' => $request->transport_secondary_value ?? 0,
            ],
            'description' => $request->description,
        ];

        if (! $feeConcession) {
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        $meta = $feeConcession?->meta ?? [];
        $meta['code'] = $request->code;
        $meta['enable_secondary_concession'] = $request->boolean('enable_secondary_concession');
        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function updateRecords(Request $request, FeeConcession $feeConcession): void
    {
        $feeHeadIds = [];
        foreach ($request->records as $record) {
            $feeConcessionRecord = FeeConcessionRecord::firstOrCreate([
                'fee_concession_id' => $feeConcession->id,
                'fee_head_id' => Arr::get($record, 'head.id'),
            ]);

            $feeHeadIds[] = Arr::get($record, 'head.id');

            $feeConcessionRecord->type = Arr::get($record, 'type');
            $feeConcessionRecord->value = Arr::get($record, 'value', 0);
            $feeConcessionRecord->setMeta([
                'secondary_type' => Arr::get($record, 'secondary_type', 'percent'),
                'secondary_value' => Arr::get($record, 'secondary_value', 0),
            ]);
            $feeConcessionRecord->save();
        }

        FeeConcessionRecord::query()
            ->whereFeeConcessionId($feeConcession->id)
            ->whereNotIn('fee_head_id', $feeHeadIds)
            ->delete();
    }

    private function isAssigned(FeeConcession $feeConcession): void
    {
        if (auth()->user()->is_default) {
            $feeConcessionExists = Fee::query()
                ->whereFeeConcessionId($feeConcession->id)
                ->where('paid', '>', 0)
                ->exists();
        } else {
            $feeConcessionExists = Fee::query()
                ->whereFeeConcessionId($feeConcession->id)
                ->exists();
        }

        if ($feeConcessionExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('finance.fee_concession.fee_concession'), 'dependency' => trans('student.student')])]);
        }
    }

    public function update(Request $request, FeeConcession $feeConcession): void
    {
        $this->isAssigned($feeConcession);

        \DB::beginTransaction();

        $feeConcession->forceFill($this->formatParams($request, $feeConcession))->save();

        $this->updateRecords($request, $feeConcession);

        (new UpdateStudentFeeConcession)->execute($feeConcession);

        \DB::commit();
    }

    public function deletable(FeeConcession $feeConcession, $validate = false): ?bool
    {
        $this->isAssigned($feeConcession);

        return true;
    }
}
