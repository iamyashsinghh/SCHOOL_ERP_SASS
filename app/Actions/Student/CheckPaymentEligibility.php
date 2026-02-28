<?php

namespace App\Actions\Student;

use App\Models\Tenant\Finance\DayClosure;
use App\Models\Tenant\Student\Student;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CheckPaymentEligibility
{
    public function execute(Student $student, array $options = [])
    {
        if ($student->getMeta('fee_locked_at')) {
            throw ValidationException::withMessages(['message' => trans('student.payment.could_not_make_payment_as_fee_locked')]);
        }

        if (empty(auth()->user())) {
            return;
        }

        if (auth()->user()?->hasAnyRole(['student', 'guardian'])) {
            return;
        }

        $date = Arr::get($options, 'date');

        if (empty($date)) {
            $date = today()->toDateString();
        }

        $dayClosure = DayClosure::query()
            ->whereUserId(auth()->id())
            ->where('date', $date)
            ->first();

        if ($dayClosure) {
            throw ValidationException::withMessages(['message' => trans('finance.day_closure.could_not_make_payment_after_closure')]);
        }
    }
}
