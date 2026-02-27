<?php

namespace App\Actions\Finance;

use App\Models\Finance\DayClosure;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CheckTransactionEligibility
{
    public function execute(array $options = [])
    {
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
