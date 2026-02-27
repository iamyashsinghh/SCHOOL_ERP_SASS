<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\Transport\Vehicle\InchargeType;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Incharge;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Http\Request;

class InchargeService
{
    public function preRequisite(Request $request)
    {
        $vehicles = VehicleResource::collection(Vehicle::query()
            ->byTeam()
            ->get());

        $types = InchargeType::getOptions();

        return compact('vehicles', 'types');
    }

    public function create(Request $request): Incharge
    {
        \DB::beginTransaction();

        $vehicleIncharge = Incharge::forceCreate($this->formatParams($request));

        \DB::commit();

        return $vehicleIncharge;
    }

    private function formatParams(Request $request, ?Incharge $vehicleIncharge = null): array
    {
        $formatted = [
            'model_type' => 'Vehicle',
            'model_id' => $request->vehicle_id,
            'employee_id' => $request->employee_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'remarks' => $request->remarks,
        ];

        $meta = $vehicleIncharge?->meta ?? [];
        $meta['type'] = $request->type;
        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Incharge $vehicleIncharge): void
    {
        \DB::beginTransaction();

        $vehicleIncharge->forceFill($this->formatParams($request, $vehicleIncharge))->save();

        \DB::commit();
    }

    public function deletable(Incharge $courseIncharge): void
    {
        //
    }
}
