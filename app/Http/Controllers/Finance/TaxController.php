<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\TaxRequest;
use App\Http\Resources\Finance\TaxResource;
use App\Models\Tenant\Finance\Tax;
use App\Services\Finance\TaxListService;
use App\Services\Finance\TaxService;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:finance:config')->only(['store', 'update', 'destroy']);
    }

    public function preRequisite(TaxService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, TaxListService $service)
    {
        return $service->paginate($request);
    }

    public function store(TaxRequest $request, TaxService $service)
    {
        $tax = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('finance.tax.tax')]),
            'tax' => TaxResource::make($tax),
        ]);
    }

    public function show(Tax $tax, TaxService $service)
    {
        return TaxResource::make($tax);
    }

    public function update(TaxRequest $request, Tax $tax, TaxService $service)
    {
        $service->update($tax, $request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('finance.tax.tax')]),
        ]);
    }

    public function destroy(Tax $tax, TaxService $service)
    {
        $service->deletable($tax);

        $tax->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('finance.tax.tax')]),
        ]);
    }
}
