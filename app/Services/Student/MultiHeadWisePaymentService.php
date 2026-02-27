<?php

namespace App\Services\Student;

use App\Actions\Student\CheckPaymentEligibility;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MultiHeadWisePaymentService
{
    public function makePayment(Request $request, Student $student): void
    {
        (new CheckPaymentEligibility)->execute($student);

        throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);
    }
}
