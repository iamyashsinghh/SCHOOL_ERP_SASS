<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Student;
use App\Services\Student\FeeActionService;
use Illuminate\Http\Request;

class FeeActionController extends Controller
{
    public function lockUnlock(Request $request, string $student, FeeActionService $service)
    {
        $student = Student::findSummaryByUuidOrFail($student);

        $this->authorize('lockUnlockFee', $student);

        $service->lockUnlock($request, $student);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.fee.fee')]),
        ]);
    }
}
