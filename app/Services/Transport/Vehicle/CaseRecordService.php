<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\OptionType;
use App\Http\Resources\OptionResource;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\CaseRecord;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CaseRecordService
{
    use FormatCodeNumber;

    private function codeNumber(): array
    {
        $numberPrefix = config('config.transport.vehicle_case_number_prefix');
        $numberSuffix = config('config.transport.vehicle_case_number_suffix');
        $digit = config('config.transport.vehicle_case_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) CaseRecord::query()
            ->byTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(Request $request): array
    {
        $vehicles = VehicleResource::collection(Vehicle::query()
            ->byTeam()
            ->get());

        $types = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_CASE_TYPE)
            ->get());

        return compact('vehicles', 'types');
    }

    public function create(Request $request): CaseRecord
    {
        \DB::beginTransaction();

        $vehicleCaseRecord = CaseRecord::forceCreate($this->formatParams($request));

        $vehicleCaseRecord->addMedia($request);

        \DB::commit();

        return $vehicleCaseRecord;
    }

    private function formatParams(Request $request, ?CaseRecord $vehicleCaseRecord = null): array
    {
        $formatted = [
            'vehicle_id' => $request->vehicle_id,
            'type_id' => $request->type_id,
            'title' => $request->title,
            'date' => $request->date,
            'penalty' => $request->penalty,
            'location' => $request->location,
            'description' => $request->description,
            'action' => $request->action,
        ];

        if (! $vehicleCaseRecord) {
            $codeNumberDetail = $this->codeNumber();

            $formatted['number_format'] = Arr::get($codeNumberDetail, 'number_format');
            $formatted['number'] = Arr::get($codeNumberDetail, 'number');
            $formatted['code_number'] = Arr::get($codeNumberDetail, 'code_number');
        }

        return $formatted;
    }

    public function update(Request $request, CaseRecord $vehicleCaseRecord): void
    {
        \DB::beginTransaction();

        $vehicleCaseRecord->forceFill($this->formatParams($request, $vehicleCaseRecord))->save();

        $vehicleCaseRecord->updateMedia($request);

        \DB::commit();
    }

    public function deletable(CaseRecord $vehicleCaseRecord): void
    {
        //
    }
}
