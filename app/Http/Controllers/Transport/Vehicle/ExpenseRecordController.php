<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\Vehicle\ExpenseRecordRequest;
use App\Http\Resources\Transport\Vehicle\ExpenseRecordResource;
use App\Models\Transport\Vehicle\ExpenseRecord;
use App\Services\Transport\Vehicle\ExpenseRecordListService;
use App\Services\Transport\Vehicle\ExpenseRecordService;
use Illuminate\Http\Request;

class ExpenseRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, ExpenseRecordService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, ExpenseRecordListService $service)
    {
        $this->authorize('viewAny', ExpenseRecord::class);

        return $service->paginate($request);
    }

    public function store(ExpenseRecordRequest $request, ExpenseRecordService $service)
    {
        $this->authorize('create', ExpenseRecord::class);

        $vehicleExpenseRecord = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('transport.vehicle.expense_record.expense_record')]),
            'vehicle' => ExpenseRecordResource::make($vehicleExpenseRecord),
        ]);
    }

    public function show(string $vehicleExpenseRecord, ExpenseRecordService $service)
    {
        $vehicleExpenseRecord = ExpenseRecord::findByUuidOrFail($vehicleExpenseRecord);

        $this->authorize('view', $vehicleExpenseRecord);

        $vehicleExpenseRecord->load('vehicle', 'type', 'reminder.users', 'media');

        return ExpenseRecordResource::make($vehicleExpenseRecord);
    }

    public function update(ExpenseRecordRequest $request, string $vehicleExpenseRecord, ExpenseRecordService $service)
    {
        $vehicleExpenseRecord = ExpenseRecord::findByUuidOrFail($vehicleExpenseRecord);

        $this->authorize('update', $vehicleExpenseRecord);

        $service->update($request, $vehicleExpenseRecord);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('transport.vehicle.expense_record.expense_record')]),
        ]);
    }

    public function destroy(string $vehicleExpenseRecord, ExpenseRecordService $service)
    {
        $vehicleExpenseRecord = ExpenseRecord::findByUuidOrFail($vehicleExpenseRecord);

        $this->authorize('delete', $vehicleExpenseRecord);

        $service->deletable($vehicleExpenseRecord);

        $vehicleExpenseRecord->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('transport.vehicle.expense_record.expense_record')]),
        ]);
    }

    public function downloadMedia(string $vehicleExpenseRecord, string $uuid, ExpenseRecordService $service)
    {
        $vehicleExpenseRecord = ExpenseRecord::findByUuidOrFail($vehicleExpenseRecord);

        $this->authorize('view', $vehicleExpenseRecord);

        return $vehicleExpenseRecord->downloadMedia($uuid);
    }
}
