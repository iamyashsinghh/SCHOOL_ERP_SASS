<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\OptionType;
use App\Enums\Transport\Vehicle\FuelType;
use App\Enums\Transport\Vehicle\Ownership;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\TripRecord;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function preRequisite(Request $request)
    {
        $fuelTypes = FuelType::getOptions();

        $ownerships = Ownership::getOptions();

        $types = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::VEHICLE_TYPE)
            ->get());

        $documentTypes = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_DOCUMENT_TYPE)
            ->where('meta->has_number', true)
            ->get());

        return compact('fuelTypes', 'ownerships', 'types', 'documentTypes');
    }

    public function create(Request $request): Vehicle
    {
        \DB::beginTransaction();

        $vehicle = Vehicle::forceCreate($this->formatParams($request));

        \DB::commit();

        return $vehicle;
    }

    private function formatParams(Request $request, ?Vehicle $vehicle = null): array
    {
        $formatted = [
            'name' => $request->name,
            'type_id' => $request->type_id,
            'registration' => [
                'number' => $request->registration_number,
                'place' => $request->registration_place,
                'date' => $request->registration_date,
                'chassis_number' => $request->chassis_number,
                'engine_number' => $request->engine_number,
                'cubic_capacity' => $request->cubic_capacity,
                'color' => $request->color,
            ],
            'model_number' => $request->model_number,
            'make' => $request->make,
            'class' => $request->class,
            'fuel_type' => $request->fuel_type,
            'fuel_capacity' => $request->fuel_capacity,
            'seating_capacity' => $request->seating_capacity,
            'max_seating_allowed' => $request->max_seating_allowed,
            'owner' => [
                'ownership' => $request->ownership,
                'ownership_date' => $request->ownership_date,
                'name' => $request->owner_name,
                'address' => $request->owner_address,
                'phone' => $request->owner_phone,
                'email' => $request->owner_email,
            ],
        ];

        if (! $vehicle) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Vehicle $vehicle): void
    {
        \DB::beginTransaction();

        $vehicle->forceFill($this->formatParams($request, $vehicle))->save();

        \DB::commit();
    }

    public function deletable(Vehicle $vehicle): bool
    {
        $vehicleDocumentExists = \DB::table('documents')
            ->whereDocumentableType(Vehicle::class)
            ->whereDocumentableId($vehicle->id)
            ->exists();

        if ($vehicleDocumentExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('transport.vehicle.vehicle'), 'dependency' => trans('transport.vehicle.document.document')])]);
        }

        $vehicleTripRecordExists = \DB::table('vehicle_travel_records')
            ->whereVehicleId($vehicle->id)
            ->exists();

        if ($vehicleTripRecordExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('transport.vehicle.vehicle'), 'dependency' => trans('transport.vehicle.travel_record.travel_record')])]);
        }

        $vehicleFuelRecordExists = \DB::table('vehicle_fuel_records')
            ->whereVehicleId($vehicle->id)
            ->exists();

        if ($vehicleFuelRecordExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('transport.vehicle.vehicle'), 'dependency' => trans('transport.vehicle.fuel_record.fuel_record')])]);
        }

        $vehicleServiceRecordExists = \DB::table('vehicle_service_records')
            ->whereVehicleId($vehicle->id)
            ->exists();

        if ($vehicleServiceRecordExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('transport.vehicle.vehicle'), 'dependency' => trans('transport.vehicle.service_record.service_record')])]);
        }

        return true;
    }

    public function getData(Vehicle $vehicle): array
    {
        $data = [];

        $currentLog = TripRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('date', today()->toDateString())
            ->orderBy('log', 'desc')
            ->first();

        $data['current_log'] = $currentLog?->log ?? 0;

        $currentWeekRunning = $this->getPeriodRunning($vehicle, today()->subWeek(1)->subDay(1), today()->subDay(1));
        $previousWeekRunning = $this->getPeriodRunning($vehicle, today()->subWeek(2)->subDay(1), today()->subWeek(1)->subDay(1));

        $currentMonthRunning = $this->getPeriodRunning($vehicle, today()->subMonth(1)->subDay(1), today()->subDay(1));
        $previousMonthRunning = $this->getPeriodRunning($vehicle, today()->subMonth(2)->subDay(1), today()->subMonth(1)->subDay(1));

        $data['current_week_running'] = $currentWeekRunning;
        $data['previous_week_running'] = $previousWeekRunning;

        $data['current_month_running'] = $currentMonthRunning;
        $data['previous_month_running'] = $previousMonthRunning;

        return $data;
    }

    private function getPeriodRunning(Vehicle $vehicle, Carbon $startDate, Carbon $endDate)
    {
        $logs = TripRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->pluck('log');

        $running = $logs->isNotEmpty()
            ? $logs->last() - $logs->first()
            : 0;

        return $running;
    }
}
