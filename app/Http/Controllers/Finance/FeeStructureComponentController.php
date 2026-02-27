<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FeeStructureComponentRequest;
use App\Http\Resources\Finance\FeeInstallmentRecordResource;
use App\Http\Resources\Finance\FeeStructureComponentResource;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\FeeStructureComponent;
use App\Services\Finance\FeeStructureComponentListService;
use App\Services\Finance\FeeStructureComponentService;
use Illuminate\Http\Request;

class FeeStructureComponentController extends Controller
{
    public function preRequisite(FeeStructureComponentService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, FeeStructureComponentListService $service)
    {
        $this->authorize('viewAny', FeeStructure::class);

        return $service->paginate($request);
    }

    public function store(FeeStructureComponentRequest $request, FeeStructureComponentService $service)
    {
        $this->authorize('create', FeeStructureComponent::class);

        $feeStructureComponent = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('finance.fee_component.fee_component')]),
            'fee_component' => FeeStructureComponentResource::make($feeStructureComponent),
        ]);
    }

    public function show(string $feeStructureComponent, FeeStructureComponentService $service): FeeInstallmentRecordResource
    {
        $feeStructureComponent = $service->findByUuidOrFail($feeStructureComponent);

        return FeeInstallmentRecordResource::make($feeStructureComponent);
    }

    public function update(FeeStructureComponentRequest $request, string $feeStructureComponent, FeeStructureComponentService $service)
    {
        $feeStructureComponent = $service->findByUuidOrFail($feeStructureComponent);

        $this->authorize('update', $feeStructureComponent->installment->structure);

        $service->update($request, $feeStructureComponent);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('finance.fee_component.fee_component')]),
        ]);
    }

    public function destroy(string $feeStructureComponent, FeeStructureComponentService $service)
    {
        $feeStructureComponent = $service->findByUuidOrFail($feeStructureComponent);

        $this->authorize('delete', $feeStructureComponent->installment->structure);

        $service->deletable($feeStructureComponent);

        $feeStructureComponent->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('finance.fee_component.fee_component')]),
        ]);
    }
}
