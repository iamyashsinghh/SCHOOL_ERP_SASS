<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student\Student;
use App\Services\Contact\PhotoService;
use App\Services\Student\PhotoService as StudentPhotoService;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction');
    }

    public function preRequisite(Request $request, StudentPhotoService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function fetch(Request $request, StudentPhotoService $service)
    {
        return $service->fetch($request);
    }

    public function upload(Request $request, string $student, PhotoService $service)
    {
        $student = Student::findByUuidOrFail($student);

        $this->authorize('manageRecord', $student);

        $photoUrl = $service->upload($request, $student->contact);

        return response()->success([
            'message' => trans('global.uploaded', ['attribute' => trans('contact.props.photo')]),
            'image' => $photoUrl,
        ]);
    }

    public function remove(Request $request, string $student, PhotoService $service)
    {
        $student = Student::findByUuidOrFail($student);

        $this->authorize('manageRecord', $student);

        $photoUrl = $service->remove($request, $student->contact);

        return response()->success([
            'message' => trans('global.removed', ['attribute' => trans('contact.props.photo')]),
            'image' => $photoUrl,
        ]);
    }
}
