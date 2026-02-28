<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student\Student;
use App\Services\Student\GuardianActionService;
use Illuminate\Http\Request;

class GuardianActionController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function makePrimary(Request $request, string $student, string $guardian, GuardianActionService $service)
    {
        $student = Student::findByUuidOrFail($student);

        $this->authorize('manageRecord', $student);

        $service->makePrimary($request, $student, $guardian);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('guardian.guardian')]),
        ]);
    }
}
