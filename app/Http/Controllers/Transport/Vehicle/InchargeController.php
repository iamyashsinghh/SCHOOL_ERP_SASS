<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\Vehicle\InchargeRequest;
use App\Http\Resources\Transport\Vehicle\InchargeResource;
use App\Models\Incharge;
use App\Services\Transport\Vehicle\InchargeListService;
use App\Services\Transport\Vehicle\InchargeService;
use Illuminate\Http\Request;

class InchargeController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, InchargeService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, InchargeListService $service)
    {
        $this->authorize('viewAny', [Incharge::class, 'vehicle']);

        return $service->paginate($request);
    }

    public function store(InchargeRequest $request, InchargeService $service)
    {
        $this->authorize('create', [Incharge::class, 'vehicle']);

        $vehicleIncharge = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('transport.vehicle.incharge.incharge')]),
            'vehicle_incharge' => InchargeResource::make($vehicleIncharge),
        ]);
    }

    public function show(string $vehicleIncharge, InchargeService $service)
    {
        $vehicleIncharge = Incharge::findByUuidOrFail($vehicleIncharge);

        $this->authorize('view', [$vehicleIncharge, 'vehicle']);

        $vehicleIncharge->load('model');

        return InchargeResource::make($vehicleIncharge);
    }

    public function update(InchargeRequest $request, string $vehicleIncharge, InchargeService $service)
    {
        $vehicleIncharge = Incharge::findByUuidOrFail($vehicleIncharge);

        $this->authorize('update', [$vehicleIncharge, 'vehicle']);

        $service->update($request, $vehicleIncharge, 'vehicle');

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('transport.vehicle.incharge.incharge')]),
        ]);
    }

    public function destroy(string $vehicleIncharge, InchargeService $service)
    {
        $vehicleIncharge = Incharge::findByUuidOrFail($vehicleIncharge);

        $this->authorize('delete', [$vehicleIncharge, 'vehicle']);

        $service->deletable($vehicleIncharge);

        $vehicleIncharge->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('transport.vehicle.incharge.incharge')]),
        ]);
    }
}
