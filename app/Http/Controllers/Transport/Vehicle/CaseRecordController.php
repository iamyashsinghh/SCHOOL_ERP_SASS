<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\Vehicle\CaseRecordRequest;
use App\Http\Resources\Transport\Vehicle\CaseRecordResource;
use App\Models\Transport\Vehicle\CaseRecord;
use App\Services\Transport\Vehicle\CaseRecordListService;
use App\Services\Transport\Vehicle\CaseRecordService;
use Illuminate\Http\Request;

class CaseRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, CaseRecordService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, CaseRecordListService $service)
    {
        $this->authorize('viewAny', CaseRecord::class);

        return $service->paginate($request);
    }

    public function store(CaseRecordRequest $request, CaseRecordService $service)
    {
        $this->authorize('create', CaseRecord::class);

        $vehicleCaseRecord = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('transport.vehicle.case_record.case_record')]),
            'vehicle' => CaseRecordResource::make($vehicleCaseRecord),
        ]);
    }

    public function show(string $vehicleCaseRecord, CaseRecordService $service)
    {
        $vehicleCaseRecord = CaseRecord::findByUuidOrFail($vehicleCaseRecord);

        $this->authorize('view', $vehicleCaseRecord);

        $vehicleCaseRecord->load('vehicle', 'type', 'media');

        return CaseRecordResource::make($vehicleCaseRecord);
    }

    public function update(CaseRecordRequest $request, string $vehicleCaseRecord, CaseRecordService $service)
    {
        $vehicleCaseRecord = CaseRecord::findByUuidOrFail($vehicleCaseRecord);

        $this->authorize('update', $vehicleCaseRecord);

        $service->update($request, $vehicleCaseRecord);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('transport.vehicle.case_record.case_record')]),
        ]);
    }

    public function destroy(string $vehicleCaseRecord, CaseRecordService $service)
    {
        $vehicleCaseRecord = CaseRecord::findByUuidOrFail($vehicleCaseRecord);

        $this->authorize('delete', $vehicleCaseRecord);

        $service->deletable($vehicleCaseRecord);

        $vehicleCaseRecord->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('transport.vehicle.case_record.case_record')]),
        ]);
    }

    public function downloadMedia(string $vehicleCaseRecord, string $uuid, CaseRecordService $service)
    {
        $vehicleCaseRecord = CaseRecord::findByUuidOrFail($vehicleCaseRecord);

        $this->authorize('view', $vehicleCaseRecord);

        return $vehicleCaseRecord->downloadMedia($uuid);
    }
}
