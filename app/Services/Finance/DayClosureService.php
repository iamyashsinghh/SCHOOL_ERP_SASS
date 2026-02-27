<?php

namespace App\Services\Finance;

use App\Models\Finance\DayClosure;
use App\Models\Finance\TransactionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DayClosureService
{
    public function preRequisite(): array
    {
        $denominations = collect(explode(',', config('config.finance.currency_denominations')))->map(function ($item) {
            return Str::of($item)->trim()->value();
        })->sort()->values();

        return compact('denominations');
    }

    public function getDateWiseCollection(Request $request): array
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->date;

        $userCollectedAmount = TransactionPayment::query()
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('date', $date)
                    ->where('user_id', auth()->id())
                    ->succeeded();
            })
            ->whereHas('method', function ($q) {
                $q->where('name', 'Cash');
            })
            ->get()
            ->sum('amount.value');

        return [
            'date' => \Cal::date($date),
            'user_collected_amount' => \Price::from($userCollectedAmount),
        ];
    }

    public function deletable(DayClosure $dayClosure): void
    {
        $nextDayClosure = DayClosure::query()
            ->where('date', '>', $dayClosure->date->value)
            ->where('user_id', $dayClosure->user_id)
            ->exists();

        if ($nextDayClosure) {
            throw ValidationException::withMessages(['message' => __('finance.day_closure.could_not_delete_previous_closure')]);
        }
    }

    public function delete(DayClosure $dayClosure): void
    {
        \DB::beginTransaction();

        $dayClosure->delete();

        \DB::commit();
    }
}
