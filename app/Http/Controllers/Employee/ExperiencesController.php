<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ExperiencesRequest;
use App\Http\Resources\Employee\ExperiencesResource;
use App\Models\Employee\Employee;
use App\Services\Employee\ExperiencesListService;
use App\Services\Employee\ExperiencesService;
use Illuminate\Http\Request;

class ExperiencesController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:employee:read');
    }

    public function preRequisite(Request $request, ExperiencesService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, ExperiencesListService $service)
    {
        return $service->paginate($request);
    }

    public function store(ExperiencesRequest $request, ExperiencesService $service)
    {
        $employee = Employee::find($request->employee_id);

        $this->authorize('selfService', $employee);

        $experience = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('employee.experience.experience')]),
            'experience' => ExperiencesResource::make($experience),
        ]);
    }

    public function show(Request $request, string $experience, ExperiencesService $service)
    {
        $experience = $service->findByUuidOrFail($experience);

        $employee = $service->findEmployee($experience);

        $experience->employee = $employee;

        $experience->load('employmentType', 'media');

        return ExperiencesResource::make($experience);
    }

    public function update(ExperiencesRequest $request, string $experience, ExperiencesService $service)
    {
        $experience = $service->findByUuidOrFail($experience);

        $employee = $service->findEmployee($experience);

        $this->authorize('selfService', $employee);

        $service->update($request, $experience);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('employee.experience.experience')]),
        ]);
    }

    public function destroy(Request $request, string $experience, ExperiencesService $service)
    {
        $experience = $service->findByUuidOrFail($experience);

        $employee = $service->findEmployee($experience);

        $this->authorize('manageEmployeeRecord', $employee);

        $service->deletable($request, $experience);

        $experience->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('employee.experience.experience')]),
        ]);
    }

    public function downloadMedia(string $experience, string $uuid, ExperiencesService $service)
    {
        $experience = $service->findByUuidOrFail($experience);

        $employee = $service->findEmployee($experience);

        return $experience->downloadMedia($uuid);
    }
}
