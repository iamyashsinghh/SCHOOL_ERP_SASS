<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student\Registration;
use App\Services\Student\RegistrationVerifyService;
use Illuminate\Http\Request;

class RegistrationVerifyController extends Controller
{
    public function verify(Request $request, Registration $registration, RegistrationVerifyService $service)
    {
        $this->authorize('verify', $registration);

        $service->verify($request, $registration);

        return response()->success([
            'message' => trans('global.verified', ['attribute' => trans('student.registration.registration')]),
        ]);
    }
}
