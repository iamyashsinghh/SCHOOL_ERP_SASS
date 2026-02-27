<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\OptionType;
use App\Http\Resources\OptionResource;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Option;
use App\Models\Transport\Vehicle\TripRecord;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Http\Request;

class TripRecordService
{
    public function preRequisite(Request $request): array
    {
        $vehicles = VehicleResource::collection(Vehicle::query()
            ->byTeam()
            ->get());

        $purposes = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::VEHICLE_TRIP_PURPOSE->value)
            ->get());

        return compact('vehicles', 'purposes');
    }

    public function create(Request $request): TripRecord
    {
        \DB::beginTransaction();

        $vehicleTripRecord = TripRecord::forceCreate($this->formatParams($request));

        $vehicleTripRecord->addMedia($request);

        \DB::commit();

        return $vehicleTripRecord;
    }

    private function formatParams(Request $request, ?TripRecord $vehicleTripRecord = null): array
    {
        $formatted = [
            'vehicle_id' => $request->vehicle_id,
            'purpose_id' => $request->purpose_id,
            'date' => $request->date,
            'log' => $request->log,
            'remarks' => $request->remarks,
        ];

        if (! $vehicleTripRecord) {
            //
        }

        return $formatted;
    }

    public function update(Request $request, TripRecord $vehicleTripRecord): void
    {
        \DB::beginTransaction();

        $vehicleTripRecord->forceFill($this->formatParams($request, $vehicleTripRecord))->save();

        $vehicleTripRecord->updateMedia($request);

        \DB::commit();
    }

    public function deletable(TripRecord $vehicleTripRecord): void
    {
        //
    }
}
