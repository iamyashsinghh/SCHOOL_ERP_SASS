<?php

namespace App\Actions\Student;

use App\Enums\Finance\DefaultFeeHead;
use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Student\Fee as StudentFee;
use App\Models\Tenant\Student\FeeRecord;
use App\Models\Tenant\Transport\Circle;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BulkUpdateFeeInstallment
{
    public function execute(StudentFee $studentFee, Collection $feeConcessions, ?Circle $transportCircle, array $params = []): void
    {
        // $this->updateConcession($studentFee, $feeConcession);

        $this->updateTransportCircle($studentFee, $transportCircle, $feeConcessions, $params);

        foreach (Arr::get($params, 'opted_fee_heads', []) as $optedFeeHead) {
            $this->updateFeeHead($studentFee, $optedFeeHead, $feeConcessions, $params);
        }

        $studentFee->refresh();

        $studentFee->total = $studentFee->getInstallmentTotal()->value;
        $studentFee->save();
    }

    private function updateConcession(StudentFee $studentFee, ?FeeConcession $feeConcession): void
    {
        if (! $feeConcession) {
            return;
        }

        if ($studentFee->getMeta('has_custom_concession')) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_set_concession_for_existing_custom_concession')]);
        }

        $feeInstallment = $studentFee->installment;

        if ($feeInstallment->getMeta('has_no_concession')) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_set_concession_for_no_concession')]);
        }

        if ($studentFee->fee_concession_id == $feeConcession->id) {
            return;
        }

        if ($studentFee->paid->value > 0) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_update_concession_for_paid_fee')]);
        }

        // update concession
    }

    private function updateTransportCircle(StudentFee $studentFee, ?Circle $transportCircle, ?Collection $feeConcessions, array $params = []): void
    {
        if (! $transportCircle) {
            return;
        }

        $direction = Arr::get($params, 'direction');

        if ($studentFee->transport_circle_id == $transportCircle->id && $studentFee->transport_direction == $direction) {
            return;
        }

        $transportFeePayment = $studentFee->payments->firstWhere('default_fee_head', DefaultFeeHead::TRANSPORT_FEE->value);

        if ($transportFeePayment) {
            return;
        }

        $feeConcession = $feeConcessions->firstWhere('id', $studentFee->fee_concession_id);

        $feeInstallment = $studentFee->installment;

        $studentFee->transport_circle_id = $transportCircle?->id;
        $studentFee->transport_direction = $direction;

        $transportFeeAmount = (new GetTransportFeeAmount)->execute(
            studentFee: $studentFee,
            feeInstallment: $feeInstallment
        );

        $transportFeeConcessionAmount = (new GetTransportConcessionFeeAmount)->execute(
            feeConcession: $feeConcession,
            transportFeeAmount: $transportFeeAmount
        );

        $studentFeeRecord = FeeRecord::firstOrCreate([
            'student_fee_id' => $studentFee->id,
            'default_fee_head' => DefaultFeeHead::TRANSPORT_FEE->value,
        ]);

        $studentFeeRecord->amount = $transportFeeAmount;
        $studentFeeRecord->concession = $transportFeeConcessionAmount;
        $studentFeeRecord->save();

        $studentFee->save();
    }

    private function updateFeeHead(StudentFee $studentFee, int $optedFeeHead, ?Collection $feeConcessions, array $params = []): void
    {
        $studentFeeRecord = $studentFee->records->firstWhere('fee_head_id', $optedFeeHead);

        if ($studentFeeRecord) {
            return;
        }

        $feeInstallment = $studentFee->installment;

        $feeInstallmentRecord = $feeInstallment->records->firstWhere('fee_head_id', $optedFeeHead);

        if (! $feeInstallmentRecord) {
            return;
        }

        if (! $feeInstallmentRecord->is_optional) {
            return;
        }

        if ($feeInstallmentRecord->getMeta('applicable_to') == 'new' && ! Arr::get($params, 'is_new_student')) {
            return;
        }

        if ($feeInstallmentRecord->getMeta('applicable_to') == 'old' && ! Arr::get($params, 'is_old_student')) {
            return;
        }

        if ($feeInstallmentRecord->getMeta('applicable_to_gender') == 'male' && ! Arr::get($params, 'is_male_student')) {
            return;
        }

        if ($feeInstallmentRecord->getMeta('applicable_to_gender') == 'female' && ! Arr::get($params, 'is_female_student')) {
            return;
        }

        if ($feeInstallmentRecord->amount->value == 0) {
            return;
        }

        $feeConcession = $feeConcessions->firstWhere('id', $studentFee->fee_concession_id);

        $concessionAmount = (new CalculateFeeConcession)->execute(
            feeConcession: $feeConcession,
            feeHeadId: $optedFeeHead,
            amount: $feeInstallmentRecord->amount->value
        );

        $studentFeeRecord = FeeRecord::forceCreate([
            'student_fee_id' => $studentFee->id,
            'fee_head_id' => $optedFeeHead,
            'amount' => $feeInstallmentRecord->amount->value,
            'concession' => $concessionAmount,
        ]);
    }
}
