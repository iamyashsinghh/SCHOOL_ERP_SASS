<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\Vehicle\TripRecordRequest;
use App\Http\Resources\Transport\Vehicle\TripRecordResource;
use App\Models\Tenant\Transport\Vehicle\TripRecord;
use App\Services\Transport\Vehicle\TripRecordListService;
use App\Services\Transport\Vehicle\TripRecordService;
use Illuminate\Http\Request;

class TripRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, TripRecordService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, TripRecordListService $service)
    {
        $this->authorize('viewAny', TripRecord::class);

        return $service->paginate($request);
    }

    public function store(TripRecordRequest $request, TripRecordService $service)
    {
        $this->authorize('create', TripRecord::class);

        $vehicleTripRecord = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('transport.vehicle.trip_record.trip_record')]),
            'vehicle' => TripRecordResource::make($vehicleTripRecord),
        ]);
    }

    public function show(string $vehicleTripRecord, TripRecordService $service)
    {
        $vehicleTripRecord = TripRecord::findByUuidOrFail($vehicleTripRecord);

        $this->authorize('view', $vehicleTripRecord);

        $vehicleTripRecord->load('vehicle', 'purpose', 'media');

        return TripRecordResource::make($vehicleTripRecord);
    }

    public function update(TripRecordRequest $request, string $vehicleTripRecord, TripRecordService $service)
    {
        $vehicleTripRecord = TripRecord::findByUuidOrFail($vehicleTripRecord);

        $this->authorize('update', $vehicleTripRecord);

        $service->update($request, $vehicleTripRecord);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('transport.vehicle.trip_record.trip_record')]),
        ]);
    }

    public function destroy(string $vehicleTripRecord, TripRecordService $service)
    {
        $vehicleTripRecord = TripRecord::findByUuidOrFail($vehicleTripRecord);

        $this->authorize('delete', $vehicleTripRecord);

        $service->deletable($vehicleTripRecord);

        $vehicleTripRecord->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('transport.vehicle.trip_record.trip_record')]),
        ]);
    }

    public function downloadMedia(string $vehicleTripRecord, string $uuid, TripRecordService $service)
    {
        $vehicleTripRecord = TripRecord::findByUuidOrFail($vehicleTripRecord);

        $this->authorize('view', $vehicleTripRecord);

        return $vehicleTripRecord->downloadMedia($uuid);
    }
}
