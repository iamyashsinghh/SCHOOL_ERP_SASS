<?php

namespace App\Http\Controllers\Transport\Vehicle\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\Vehicle\Config\ExpenseTypeRequest;
use App\Http\Resources\Transport\Vehicle\Config\ExpenseTypeResource;
use App\Models\Tenant\Option;
use App\Services\Transport\Vehicle\Config\ExpenseTypeListService;
use App\Services\Transport\Vehicle\Config\ExpenseTypeService;
use Illuminate\Http\Request;

class ExpenseTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:transport:config')->except(['index', 'show']);
    }

    public function preRequisite(Request $request, ExpenseTypeService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, ExpenseTypeListService $service)
    {
        return $service->paginate($request);
    }

    public function store(ExpenseTypeRequest $request, ExpenseTypeService $service)
    {
        $expenseType = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('transport.vehicle.expense_type.expense_type')]),
            'expense_type' => ExpenseTypeResource::make($expenseType),
        ]);
    }

    public function show(Option $expenseType, ExpenseTypeService $service)
    {
        return ExpenseTypeResource::make($expenseType);
    }

    public function update(ExpenseTypeRequest $request, Option $expenseType, ExpenseTypeService $service)
    {
        $service->update($request, $expenseType);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('transport.vehicle.expense_type.expense_type')]),
        ]);
    }

    public function destroy(Request $request, Option $expenseType, ExpenseTypeService $service)
    {
        $service->deletable($request, $expenseType);

        $expenseType->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('transport.vehicle.expense_type.expense_type')]),
        ]);
    }
}
