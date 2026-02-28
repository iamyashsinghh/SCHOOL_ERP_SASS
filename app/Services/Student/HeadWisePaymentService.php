<?php

namespace App\Services\Student;

use App\Actions\Student\CheckPaymentEligibility;
use App\Actions\Student\GetStudentFees;
use App\Actions\Student\HeadWisePayment;
use App\Jobs\Notifications\Student\SendFeePaymentConfirmedNotification;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class HeadWisePaymentService
{
    public function makePayment(Request $request, Student $student): void
    {
        (new CheckPaymentEligibility)->execute($student);

        (new GetStudentFees)->validatePreviousDue($student);

        $params = $request->all();

        $response = (new HeadWisePayment)->execute($student, $params);

        if (Arr::get($response, 'status')) {

            SendFeePaymentConfirmedNotification::dispatch([
                'student_id' => $student->id,
                'transaction_id' => Arr::get($response, 'transaction_id'),
                'team_id' => $student->team_id,
            ]);

            return;
        }

        throw ValidationException::withMessages([Arr::get($response, 'key') => Arr::get($response, 'message')]);
    }
}
