<?php

namespace App\Services\Finance;

use App\Enums\Finance\DayClosureStatus;
use App\Models\Finance\DayClosure;
use App\Models\Finance\TransactionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class MarkDayClosureService
{
    public function markDayClosure(Request $request, ?DayClosure $dayClosure = null): DayClosure
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'remarks' => 'nullable|max:1000',
            'denominations' => 'required|array|min:1',
            'denominations.*.count' => 'required|integer|min:0',
            'reason' => 'nullable|max:1000',
            'total' => 'required|integer|min:0',
        ], [
        ], [
            'date' => trans('general.date'),
            'remarks' => trans('finance.day_closure.props.remarks'),
            'denominations' => trans('finance.day_closure.props.denominations'),
            'denominations.*.count' => trans('finance.day_closure.props.count'),
            'reason' => trans('finance.day_closure.props.reason'),
            'total' => trans('finance.day_closure.props.total'),
        ]);

        $date = $request->date ?? today()->toDateString();

        $availableDenominations = explode(',', config('config.finance.currency_denominations'));

        $total = 0;
        foreach ($availableDenominations as $denomination) {
            $inputDenomination = collect($request->denominations)
                ->firstWhere('value', $denomination);

            $total += Arr::get($inputDenomination, 'count', 0) * $denomination;
        }

        if ($total != $request->total) {
            throw ValidationException::withMessages(['message' => trans('finance.total_mismatch')]);
        }

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

        if ($userCollectedAmount != $total) {
            if (empty($request->reason)) {
                throw ValidationException::withMessages(['reason' => trans('validation.required', ['attribute' => trans('finance.day_closure.props.reason')])]);
            }
            // throw ValidationException::withMessages(['message' => trans('finance.day_closure.total_mismatch_with_collected_amount', ['amount' => \Price::from($total)->formatted, 'collected_amount' => \Price::from($userCollectedAmount)->formatted])]);
        }

        $existingRecord = DayClosure::query()
            ->byTeam()
            ->when($dayClosure, function ($q) use ($dayClosure) {
                $q->where('id', '!=', $dayClosure->id);
            })
            ->whereUserId(auth()->id())
            ->where('date', $date)
            ->exists();

        if ($existingRecord && ! $dayClosure) {
            throw ValidationException::withMessages(['message' => trans('finance.day_closure.already_marked')]);
        }

        $dayClosure = DayClosure::firstOrCreate([
            'team_id' => auth()->user()->current_team_id,
            'user_id' => auth()->id(),
            'date' => $date,
        ]);

        $dayClosure->remarks = $request->remarks;
        $dayClosure->denominations = collect($request->denominations)->map(fn ($denomination) => [
            'name' => Arr::get($denomination, 'value'),
            'count' => Arr::get($denomination, 'count', 0),
        ]);
        $dayClosure->total = $request->total;
        $dayClosure->status = DayClosureStatus::SUBMITTED;
        $dayClosure->meta = [
            'user_collected_amount' => $userCollectedAmount,
            'reason' => $request->reason,
            'is_amount_mismatch' => $userCollectedAmount != $total ? true : false,
            'type' => 'manual',
        ];
        $dayClosure->save();

        return $dayClosure;
    }
}
