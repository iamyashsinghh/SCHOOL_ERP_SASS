<?php

namespace App\Http\Resources\Finance\Report;

use App\Helpers\CalHelper;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeDueListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $dueOn = $request->query('due_on');

        if (! CalHelper::validateDate($dueOn)) {
            $dueOn = today();
        } else {
            $dueOn = Carbon::parse($dueOn);
        }

        $dueDate = Carbon::parse($this->final_due_date);

        $installments = $request->installments ?? collect([]);

        $filteredInstallments = $installments->where('student_id', $this->id);

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'code_number' => $this->code_number,
            'roll_number' => $this->roll_number,
            'joining_date' => \Cal::date($this->joining_date),
            'installments' => $filteredInstallments->map(function ($installment) {
                return [
                    'title' => $installment->title,
                    'total' => $installment->total,
                    'balance' => \Price::from($installment->total->value - $installment->paid->value),
                    'paid' => $installment->paid,
                ];
            }),
            'batch_name' => $this->batch_name,
            'course_name' => $this->course_name,
            'contact_number' => $this->contact_number,
            'category_name' => $this->category_name,
            'fee_group_name' => $this->fee_group_name,
            'final_due_date' => \Cal::date($this->final_due_date),
            'due_fee' => \Price::from($this->due_fee),
            'overdue_by' => abs($dueOn->diffInDays($dueDate)).' '.trans('list.durations.days'),
        ];
    }
}
