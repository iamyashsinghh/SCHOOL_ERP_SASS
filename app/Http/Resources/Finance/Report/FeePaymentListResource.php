<?php

namespace App\Http\Resources\Finance\Report;

use App\Http\Resources\Finance\TransactionPaymentResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class FeePaymentListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $type = trans('student.fee.fee');
        $joiningDate = \Cal::date($this->joining_date);
        $codeNumber = $this->code_number;

        if ($this->transactionable_type == 'Registration') {
            $type = trans('student.registration.fee');
            $codeNumber = $this->registration_code_number;
            $joiningDate = \Cal::date($this->registration_date);
        }

        $feeInstallments = [];

        $feeGroup = null;
        foreach ($this->records as $record) {
            $feeGroup = $record->model?->installment?->group?->name;
            $feeInstallments[] = $record->model?->installment?->title;
        }

        return [
            'uuid' => $this->uuid,
            'student_uuid' => $this->student_uuid,
            'registration_uuid' => $this->registration_uuid,
            'voucher_number' => $this->voucher_number,
            'amount' => $this->amount,
            'date' => $this->date,
            'ledger_name' => $this->ledger_name,
            'ledger_type' => $this->ledger_type,
            'type' => $this->type,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'code_number' => $codeNumber,
            'joining_date' => $joiningDate,
            'fee_type' => $type,
            'fee_group' => $feeGroup,
            'fee_installments' => implode(', ', $feeInstallments),
            'payment' => TransactionPaymentResource::make($this->whenLoaded('payment')),
            'fee_payments' => $this->getFeePayments(),
            'batch_name' => $this->batch_name,
            'course_name' => $this->course_name,
            'contact_number' => $this->contact_number,
            'category_name' => $this->category_name,
            'is_cancelled' => $this->cancelled_at->value ? true : false,
            'is_rejected' => $this->rejected_at->value ? true : false,
            'user' => [
                'profile' => [
                    'name' => $this->user_name,
                ],
            ],
            'status' => $this->getStatus(),
            $this->mergeWhen($this->is_online, [
                'is_online' => true,
                'reference_number' => Arr::get($this->payment_gateway, 'reference_number'),
                'gateway' => Arr::get($this->payment_gateway, 'name'),
                'is_completed' => ! $this->cancelled_at->value && ! $this->rejected_at->value && $this->processed_at->value ? true : false,
            ]),
        ];
    }

    private function getFeePayments()
    {
        if ($this->head == 'registration_fee') {
            $feeHeadName = trans('student.registration.fee');

            $feePayments[] = [
                'fee_head' => $feeHeadName,
                'amount' => $this->amount,
                'fee_head_with_amount' => $feeHeadName.' - '.$this->amount?->value,
            ];

            return $feePayments;
        }

        if (! $this->relationLoaded('feePayments')) {
            return [];
        }

        return $this->feePayments->map(function ($feePayment) {
            $feeHeadName = $feePayment->default_fee_head?->value ? trans('finance.fee.default_fee_heads.'.$feePayment->default_fee_head->value) : $feePayment->head->name;

            return [
                'fee_head' => $feeHeadName,
                'amount' => $feePayment->amount,
                'fee_head_with_amount' => $feeHeadName.' - '.$feePayment->amount?->value,
            ];
        });
    }

    private function getStatus()
    {
        if ($this->cancelled_at->value) {
            return [
                'label' => trans('finance.transaction_statuses.cancelled'),
                'value' => 'cancelled',
            ];
        }

        if ($this->rejected_at->value) {
            return [
                'label' => trans('finance.transaction_statuses.rejected'),
                'value' => 'rejected',
            ];
        }

        if (! $this->is_online) {
            return [
                'label' => trans('finance.transaction_statuses.succeed'),
                'value' => 'succeed',
            ];
        }

        if ($this->processed_at->value) {
            return [
                'label' => trans('finance.transaction_statuses.succeed'),
                'value' => 'succeed',
            ];
        }

        return [
            'label' => trans('finance.transaction_statuses.pending'),
            'value' => 'pending',
        ];
    }
}
