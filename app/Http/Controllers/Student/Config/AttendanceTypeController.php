<?php

namespace App\Http\Controllers\Student\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\Config\AttendanceTypeRequest;
use App\Http\Resources\Student\Config\AttendanceTypeResource;
use App\Models\Option;
use App\Services\Student\Config\AttendanceTypeListService;
use App\Services\Student\Config\AttendanceTypeService;
use Illuminate\Http\Request;

class AttendanceTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:student:config')->except(['index', 'show']);
    }

    public function preRequisite(Request $request, AttendanceTypeService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, AttendanceTypeListService $service)
    {
        return $service->paginate($request);
    }

    public function store(AttendanceTypeRequest $request, AttendanceTypeService $service)
    {
        $attendanceType = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('student.attendance_type.attendance_type')]),
            'attendance_type' => AttendanceTypeResource::make($attendanceType),
        ]);
    }

    public function show(Option $attendanceType, AttendanceTypeService $service)
    {
        return AttendanceTypeResource::make($attendanceType);
    }

    public function update(AttendanceTypeRequest $request, Option $attendanceType, AttendanceTypeService $service)
    {
        $service->update($request, $attendanceType);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.attendance_type.attendance_type')]),
        ]);
    }

    public function destroy(Request $request, Option $attendanceType, AttendanceTypeService $service)
    {
        $service->deletable($request, $attendanceType);

        $attendanceType->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('student.attendance_type.attendance_type')]),
        ]);
    }
}
