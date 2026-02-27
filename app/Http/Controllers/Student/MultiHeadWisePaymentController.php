<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\MultiHeadWisePaymentRequest;
use App\Models\Student\Student;
use App\Services\Student\MultiHeadWisePaymentService;

class MultiHeadWisePaymentController extends Controller
{
    public function makePayment(MultiHeadWisePaymentRequest $request, string $student, MultiHeadWisePaymentService $service)
    {
        $student = Student::findByUuidOrFail($student);

        $this->authorize('makeHeadWisePayment', $student);

        $service->makePayment($request, $student);

        return response()->success([
            'message' => trans('global.paid', ['attribute' => trans('student.fee.fee')]),
        ]);
    }
}
