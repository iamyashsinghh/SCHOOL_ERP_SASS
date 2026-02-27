<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RegistrationAssignFeeRequest;
use App\Models\Student\Registration;
use App\Services\Student\RegistrationAssignFeeService;
use Illuminate\Http\Request;

class RegistrationAssignFeeController extends Controller
{
    public function preRequisite(Request $request, Registration $registration, RegistrationAssignFeeService $service)
    {
        $this->authorize('fee', $registration);

        return response()->ok($service->preRequisite($request, $registration));
    }

    public function assignFee(RegistrationAssignFeeRequest $request, Registration $registration, RegistrationAssignFeeService $service)
    {
        $this->authorize('fee', $registration);

        $service->assignFee($request, $registration);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.registration.registration')]),
        ]);
    }
}
