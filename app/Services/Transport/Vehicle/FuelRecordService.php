<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\Finance\TransactionType;
use App\Http\Resources\Finance\LedgerResource;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Finance\Ledger;
use App\Models\Transport\Vehicle\FuelRecord;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FuelRecordService
{
    public function preRequisite(Request $request): array
    {
        $vehicles = VehicleResource::collection(Vehicle::query()
            ->byTeam()
            ->get());

        $vendors = LedgerResource::collection(Ledger::query()
            ->byTeam()
            ->subType('vendor')
            ->get());

        return compact('vehicles', 'vendors');
    }

    public function getPreviousLog(Request $request): array
    {
        $request->validate([
            'vehicle' => 'required|uuid',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $vehicleFuelRecord = FuelRecord::query()
            ->whereHas('vehicle', function ($query) use ($request) {
                $query->where('uuid', $request->vehicle);
            })
            ->where('date', '<=', $request->date)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($vehicleFuelRecord) {
            return [
                'previous_log' => $vehicleFuelRecord->log,
                'mileage' => $vehicleFuelRecord->getMeta('mileage'),
            ];
        }

        return [];
    }

    public function create(Request $request): FuelRecord
    {
        \DB::beginTransaction();

        $vehicleFuelRecord = FuelRecord::forceCreate($this->formatParams($request));

        $ledger = $request->ledger;
        if ($ledger) {
            $ledger->updateSecondaryBalance(TransactionType::RECEIPT, $request->cost);
        }

        $vehicleFuelRecord->addMedia($request);

        \DB::commit();

        return $vehicleFuelRecord;
    }

    private function formatParams(Request $request, ?FuelRecord $vehicleFuelRecord = null): array
    {
        $formatted = [
            'vehicle_id' => $request->vehicle_id,
            'vendor_id' => $request->vendor_id,
            'date' => $request->date,
            'quantity' => $request->quantity,
            'price_per_unit' => $request->price_per_unit,
            'previous_log' => $request->previous_log,
            'log' => $request->log,
            'remarks' => $request->remarks,
        ];

        $meta = $vehicleFuelRecord?->meta ?? [];

        $meta['bill_number'] = $request->bill_number;
        $meta['distance_covered'] = $request->distance_covered;
        $meta['mileage'] = $request->mileage;

        $formatted['meta'] = $meta;

        if (! $vehicleFuelRecord) {
            //
        }

        return $formatted;
    }

    public function update(Request $request, FuelRecord $vehicleFuelRecord): void
    {
        $cost = round($vehicleFuelRecord->quantity * $vehicleFuelRecord->price_per_unit->value, 2);

        $updateLedger = false;
        if ($cost != $request->cost || $vehicleFuelRecord->vendor_id != $request->vendor_id) {
            $updateLedger = true;
        }

        \DB::beginTransaction();

        if ($updateLedger) {
            $ledger = $vehicleFuelRecord->vendor;
            if ($ledger) {
                $ledger->reverseSecondaryBalance(TransactionType::RECEIPT, $cost);
            }
        }

        $vehicleFuelRecord->forceFill($this->formatParams($request, $vehicleFuelRecord))->save();

        if ($updateLedger) {
            $ledger = $request->ledger;
            if ($ledger) {
                $ledger->updateSecondaryBalance(TransactionType::RECEIPT, $request->cost);
            }
        }

        $vehicleFuelRecord->updateMedia($request);

        \DB::commit();
    }

    public function deletable(FuelRecord $vehicleFuelRecord): void
    {
        $nextVehicleFuelRecord = FuelRecord::query()
            ->where('vehicle_id', $vehicleFuelRecord->vehicle_id)
            ->where('date', '>', $vehicleFuelRecord->date->value)
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'asc')
            ->exists();

        if ($nextVehicleFuelRecord && ! auth()->user()->hasRole('admin')) {
            throw ValidationException::withMessages(['message' => trans('transport.vehicle.fuel_record.could_not_delete_intermediate_record')]);
        }
    }

    public function delete(FuelRecord $vehicleFuelRecord): void
    {
        \DB::beginTransaction();

        $cost = round($vehicleFuelRecord->quantity * $vehicleFuelRecord->price_per_unit->value, 2);

        $ledger = $vehicleFuelRecord->vendor;
        if ($ledger) {
            $ledger->reverseSecondaryBalance(TransactionType::RECEIPT, $cost);
        }

        $vehicleFuelRecord->delete();

        \DB::commit();
    }
}
