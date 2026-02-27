<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FeeComponentRequest;
use App\Http\Resources\Finance\FeeComponentResource;
use App\Services\Finance\FeeComponentListService;
use App\Services\Finance\FeeComponentService;
use Illuminate\Http\Request;

class FeeComponentController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:fee-component:manage')->only(['store', 'update', 'destroy']);
    }

    public function preRequisite(Request $request, FeeComponentService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, FeeComponentListService $service)
    {
        return $service->paginate($request);
    }

    public function store(FeeComponentRequest $request, FeeComponentService $service)
    {
        $feeComponent = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('finance.fee_component.fee_component')]),
            'fee_component' => FeeComponentResource::make($feeComponent),
        ]);
    }

    public function show(string $feeComponent, FeeComponentService $service): FeeComponentResource
    {
        $feeComponent = $service->findByUuidOrFail($feeComponent);

        $feeComponent->load('head', 'tax');

        return FeeComponentResource::make($feeComponent);
    }

    public function update(FeeComponentRequest $request, string $feeComponent, FeeComponentService $service)
    {
        $feeComponent = $service->findByUuidOrFail($feeComponent);

        $service->update($request, $feeComponent);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('finance.fee_component.fee_component')]),
        ]);
    }

    public function destroy(string $feeComponent, FeeComponentService $service)
    {
        $feeComponent = $service->findByUuidOrFail($feeComponent);

        $service->deletable($feeComponent);

        $feeComponent->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('finance.fee_component.fee_component')]),
        ]);
    }
}
