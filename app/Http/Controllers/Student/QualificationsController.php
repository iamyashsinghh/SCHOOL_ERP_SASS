<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\QualificationsRequest;
use App\Http\Resources\Student\QualificationsResource;
use App\Services\Student\QualificationsListService;
use App\Services\Student\QualificationsService;
use Illuminate\Http\Request;

class QualificationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:student:read');
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
        $qualification = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('student.qualification.qualification')]),
            'qualification' => QualificationsResource::make($qualification),
        ]);
    }

    public function show(Request $request, string $qualification, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $student = $service->findStudent($qualification);

        $qualification->student = $student;

        $qualification->load('level', 'media');

        return QualificationsResource::make($qualification);
    }

    public function update(QualificationsRequest $request, string $qualification, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $student = $service->findStudent($qualification);

        $service->update($request, $qualification);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.qualification.qualification')]),
        ]);
    }

    public function destroy(Request $request, string $qualification, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $student = $service->findStudent($qualification);

        $service->deletable($request, $qualification);

        $qualification->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('student.qualification.qualification')]),
        ]);
    }

    public function downloadMedia(string $qualification, string $uuid, QualificationsService $service)
    {
        $qualification = $service->findByUuidOrFail($qualification);

        $student = $service->findStudent($qualification);

        return $qualification->downloadMedia($uuid);
    }
}
