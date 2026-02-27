<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\MigrateAttendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\MigrateAttendanceRequest;

class MigrateAttendanceController extends Controller
{
    public function __invoke(MigrateAttendanceRequest $request, MigrateAttendance $action)
    {
        $params = $request->all();

        $action->execute($params);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.attendance.attendance')]),
        ]);
    }
}
