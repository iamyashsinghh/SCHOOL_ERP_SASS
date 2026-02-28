<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\QualificationsRequest;
use App\Http\Resources\Employee\QualificationsResource;
use App\Models\Tenant\Employee\Employee;
use App\Services\Employee\QualificationsListService;
use App\Services\Employee\QualificationsService;
use Illuminate\Http\Request;

class QualificationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:employee:read');
    }

    public function preRequisite(Request $request, QualificationsService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, QualificationsListService $service)
    {
        return $service->paginate($request);
    }

    public function store(QualificationsRequest $request, QualificationsService $service)
    {
        $employee = Employee::find($request->employee_id);

        $this->authorize('selfService', $employee);

        $qualification = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('employee.qualification.qualification')]),
            'qualification' => QualificationsResource::make($qualification),
        ]);
    }

    public function show(Request $request, string $qualification, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $employee = $service->findEmployee($qualification);

        $qualification->employee = $employee;

        $qualification->load('level', 'media');

        return QualificationsResource::make($qualification);
    }

    public function update(QualificationsRequest $request, string $qualification, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $employee = $service->findEmployee($qualification);

        $this->authorize('selfService', $employee);

        $service->update($request, $qualification);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('employee.qualification.qualification')]),
        ]);
    }

    public function destroy(Request $request, string $qualification, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $employee = $service->findEmployee($qualification);

        $this->authorize('manageEmployeeRecord', $employee);

        $service->deletable($request, $qualification);

        $qualification->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('employee.qualification.qualification')]),
        ]);
    }

    public function downloadMedia(string $qualification, string $uuid, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $employee = $service->findEmployee($qualification);

        return $qualification->downloadMedia($uuid);
    }
}
