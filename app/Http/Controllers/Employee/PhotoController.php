<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee\Employee;
use App\Services\Employee\PhotoService;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction');
    }

    public function upload(Request $request, string $employee, PhotoService $service)
    {
        $employee = Employee::findByUuidOrFail($employee);

        $this->authorize('update', $employee);

        $photoUrl = $service->upload($request, $employee);

        return response()->success([
            'message' => trans('global.uploaded', ['attribute' => trans('contact.props.photo')]),
            'image' => $photoUrl,
        ]);
    }

    public function remove(Request $request, string $employee, PhotoService $service)
    {
        $employee = Employee::findByUuidOrFail($employee);

        $this->authorize('update', $employee);

        $photoUrl = $service->remove($request, $employee);

        return response()->success([
            'message' => trans('global.removed', ['attribute' => trans('contact.props.photo')]),
            'image' => $photoUrl,
        ]);
    }
}
