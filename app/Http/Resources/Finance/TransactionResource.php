<?php

namespace App\Http\Resources\Finance;

use App\Enums\Finance\TransactionType;
use App\Http\Resources\Academic\PeriodResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\UserSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'code_number' => $this->code_number,
            'date' => $this->date,
            'amount' => $this->amount,
            'transactionable' => $this->secondary_detail,
            'type' => TransactionType::getDetail($this->type),
            $this->mergeWhen($this->head, [
                'head_org' => $this->head,
                'head' => trans('finance.transaction.heads.'.$this->head),
            ]),
            'category' => OptionResource::make($this->whenLoaded('category')),
            'period' => PeriodResource::make($this->whenLoaded('period')),
            'payment' => TransactionPaymentResource::make($this->whenLoaded('payment')),
            'payments' => TransactionPaymentResource::collection($this->whenLoaded('payments')),
            $this->mergeWhen($request->validate_clearance, [
                'pending_clearance' => $this->checkPendingClearance(),
            ]),
            $this->mergeWhen($this->is_online, [
                'is_online' => (bool) $this->is_online,
                'is_completed' => $this->processed_at->value ? true : false,
                'gateway' => Arr::get($this->payment_gateway, 'name'),
                'reference_number' => Arr::get($this->payment_gateway, 'reference_number'),
            ]),
            'sub_heading' => trans('finance.transaction.'.$this->type->value.'_label'),
            'records' => TransactionRecordResource::collection($this->whenLoaded('records')),
            'record' => TransactionRecordResource::make($this->whenLoaded('record')),
            'is_cancelled' => $this->cancelled_at->value ? true : false,
            'is_rejected' => $this->rejected_at->value ? true : false,
            'cancelled_at' => $this->cancelled_at,
            'rejected_at' => $this->rejected_at,
            'description' => $this->description,
            'description_short' => Str::limit($this->description, 50),
            'remarks' => $this->remarks,
            'cancellation_remarks' => $this->cancellation_remarks,
            $this->mergeWhen($this->rejected_at->value, [
                'rejected_date' => \Cal::date(Arr::get($this->rejection_record, 'rejected_date', $this->rejected_at->value)),
                'rejection_charge' => \Price::from(Arr::get($this->rejection_record, 'rejection_charge', 0)),
                'rejection_remarks' => $this->rejection_remarks,
            ]),
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'is_editable' => $this->can_edit,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function checkPendingClearance()
    {
        if (! $this->relationLoaded('payments')) {
            return false;
        }

        if ($this->cancelled_at->value || $this->rejected_at->value) {
            return false;
        }

        return $this->payments->contains(function ($payment) {
            return $payment->method->getConfig('has_clearing_date') && empty(Arr::get($payment->details, 'clearing_date'));
        });
    }
}
