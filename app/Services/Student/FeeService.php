<?php

namespace App\Services\Student;

use App\Actions\Student\AssignFee;
use App\Actions\Student\AssignFeeInstallment;
use App\Actions\Student\GetStudentFees;
use App\Actions\Student\UpdateFeeInstallment;
use App\Enums\Finance\LateFeeFrequency;
use App\Enums\Gender;
use App\Enums\OptionType;
use App\Enums\Student\StudentType;
use App\Enums\Transport\Direction;
use App\Http\Resources\Finance\FeeConcessionResource;
use App\Http\Resources\Finance\FeeStructureResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\Transport\CircleResource;
use App\Models\Academic\Batch;
use App\Models\Finance\FeeAllocation;
use App\Models\Finance\FeeConcession;
use App\Models\Finance\FeeInstallment;
use App\Models\Finance\FeeInstallmentRecord;
use App\Models\Finance\FeeStructure;
use App\Models\Option;
use App\Models\Student\Fee;
use App\Models\Student\Student;
use App\Models\Transport\Circle;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FeeService
{
    public function preRequisite(Request $request, Student $student): array
    {
        $directions = Direction::getOptions();

        $batch = Batch::query()
            ->byPeriod()
            ->where('id', $student->batch_id)
            ->firstOrFail();

        $batchFeeAllocation = FeeAllocation::query()
            ->whereBatchId($batch->id)
            ->first() ?? FeeAllocation::query()
            ->whereCourseId($batch->course_id)
            ->first();

        $hasBatchFeeAllocation = $batchFeeAllocation ? true : false;

        $transportCircles = CircleResource::collection(Circle::query()
            ->byPeriod($student->period_id)
            ->get());

        $feeStructures = FeeStructureResource::collection(FeeStructure::query()
            ->byPeriod()
            ->get());

        $feeConcessions = FeeConcessionResource::collection(FeeConcession::query()
            ->byPeriod($student->period_id)
            ->get());

        $feeConcessionTypes = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::FEE_CONCESSION_TYPE->value)
            ->get());

        $optionalFeeHeads = [];
        if (! $student->fee_structure_id && $hasBatchFeeAllocation) {
            $feeInstallmentRecords = FeeInstallmentRecord::query()
                ->with('head')
                ->whereHas('installment', function ($q) use ($batchFeeAllocation) {
                    $q->whereFeeStructureId($batchFeeAllocation->fee_structure_id);
                }) // remove this to get all optional fee heads
                ->whereIsOptional(1)
                ->get();

            $optionalFeeHeads = $feeInstallmentRecords->map(function ($feeInstallmentRecord) {
                return [
                    'name' => $feeInstallmentRecord->head->name,
                    'uuid' => $feeInstallmentRecord->head->uuid,
                ];
            });

            $optionalFeeHeads = collect($optionalFeeHeads)->unique('uuid')->values()->all();
        }

        $frequencies = LateFeeFrequency::getOptions();

        $studentTypes = StudentType::getOptions();

        return compact('directions', 'transportCircles', 'feeStructures', 'feeConcessions', 'feeConcessionTypes', 'optionalFeeHeads', 'frequencies', 'hasBatchFeeAllocation', 'studentTypes');
    }

    private function getFeeConcession(Student $student, ?string $feeConcessionUuid): ?FeeConcession
    {
        if (! $feeConcessionUuid) {
            return null;
        }

        return FeeConcession::query()
            ->with('records')
            ->byPeriod($student->period_id)
            ->whereUuid($feeConcessionUuid)
            ->getOrFail(trans('finance.fee_concession.fee_concession'), 'fee_concession');
    }

    private function getTransportCircle(Student $student, ?string $transportcircleUuid = null): ?Circle
    {
        if (! $transportcircleUuid) {
            return null;
        }

        return Circle::query()
            ->byPeriod($student->period_id)
            ->whereUuid($transportcircleUuid)
            ->getOrFail(trans('transport.circle.circle'), 'transport_circle');
    }

    public function setFee(Request $request, Student $student): void
    {
        if (! $student->isStudying()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        if (Fee::whereStudentId($student->id)->count()) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_set_if_fee_already_set')]);
        }

        $batch = Batch::query()
            ->byPeriod()
            ->where('id', $student->batch_id)
            ->firstOrFail();

        $feeStructure = null;
        $batchFeeAllocation = FeeAllocation::query()
            ->whereBatchId($batch->id)
            ->first() ?? FeeAllocation::query()
            ->whereCourseId($batch->course_id)
            ->first();

        if (! $batchFeeAllocation && empty($request->fee_structure)) {
            throw ValidationException::withMessages(['fee_structure' => trans('validation.required', ['attribute' => trans('finance.fee_structure.fee_structure')])]);
        }

        if (! $batchFeeAllocation && $request->fee_structure) {
            $feeStructure = FeeStructure::query()
                ->byPeriod()
                ->where('uuid', $request->fee_structure)
                ->firstOrFail();
        }

        $isNewStudent = false;
        if ($student->admission->joining_date->value == $student->start_date->value || $student->getMeta('is_new', false)) {
            $isNewStudent = true;
        }

        if ($request->student_type == 'new') {
            $isNewStudent = true;
        } elseif ($request->student_type == 'old') {
            $isNewStudent = false;
        }

        $feeConcession = $this->getFeeConcession($student, $request->fee_concession);

        $transportCircle = $this->getTransportCircle($student, $request->transport_circle);

        $student->setMeta([
            'student_type' => $request->student_type,
        ]);
        $student->save();

        \DB::beginTransaction();

        (new AssignFee)->execute(
            student: $student,
            feeConcession: $feeConcession,
            transportCircle: $transportCircle,
            feeStructure: $feeStructure,
            params: [
                'direction' => $request->direction,
                'opted_fee_heads' => $request->opted_fee_heads,
                'is_new_student' => $isNewStudent,
                'is_male_student' => $student->gender == Gender::MALE->value ? true : false,
                'is_female_student' => $student->gender == Gender::FEMALE->value ? true : false,
            ]
        );

        \DB::commit();
    }

    public function getFeeInstallment(Request $request, Student $student, string $uuid): Fee
    {
        $fee = Fee::query()
            ->whereStudentId($student->id)
            ->whereUuid($uuid)
            ->firstOrFail();

        return $fee;
    }

    public function updateFee(Request $request, Student $student): void
    {
        // throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);

        if (! $student->isStudying()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $fees = Fee::query()
            ->with('installment', 'installment.group', 'installment.transportFee.records', 'concession', 'transportCircle', 'records', 'records.head')
            ->whereStudentId($student->id)
            ->get();

        $feeConcessions = FeeConcession::query()
            ->with('records')
            ->byPeriod($student->period_id)
            ->get();

        $transportCircles = Circle::query()
            ->byPeriod($student->period_id)
            ->get();

        \DB::beginTransaction();

        $student->fee_concession_type_id = $request->fee_concession_type_id;
        $student->save();

        foreach ($request->fee_groups as $feeGroup) {
            foreach (Arr::get($feeGroup, 'fees', []) as $fee) {
                $studentFee = $fees->firstWhere('uuid', Arr::get($fee, 'uuid'));

                if (! $studentFee) {
                    throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('finance.fee_structure.installment')])]);
                }

                $feeConcession = $feeConcessions->firstWhere('uuid', Arr::get($fee, 'concession'));

                $transportCircle = $transportCircles->firstWhere('uuid', Arr::get($fee, 'transport_circle'));

                $this->validateTransportChange($studentFee, $transportCircle, [
                    'direction' => Arr::get($fee, 'direction'),
                ]);

                // logger($studentFee?->uuid);

                // if (! $studentFee) {
                //     $feeInstallment = FeeInstallment::query()
                //         ->with('records.head')
                //         ->whereUuid(Arr::get($fee, 'uuid'))
                //         ->first();

                //     if (! $feeInstallment) {
                //         throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('finance.fee_structure.installment')])]);
                //     }

                //     $optedFeeHeads = [];
                //     foreach ($feeInstallment->records as $record) {
                //         $inputFeeRecord = collect(Arr::get($fee, 'records', []))->firstWhere('head.uuid', $record->head->uuid);
                //         if ($record->is_optional && Arr::get($inputFeeRecord, 'is_applicable')) {
                //             $optedFeeHeads[] = $record->head->uuid;
                //         }
                //     }

                //     logger($optedFeeHeads);
                //     throw ValidationException::withMessages(['message' => 'testing']);

                // (new AssignFeeInstallment)->execute(
                //     student: $student,
                //     feeInstallment: $feeInstallment,
                //     feeConcession: $feeConcession,
                //     transportCircle: $transportCircle,
                //     params: array(
                //         'direction' => Arr::get($fee, 'direction'),
                //         'opted_fee_heads' => $optedFeeHeads,
                //     )
                // );
                //     continue;
                // }

                // throw ValidationException::withMessages(['message' => 'test']);

                // Allowing editing fee if paid is lesser than new amount
                // if ($studentFee->paid->value > 0) {
                //     continue;
                // }

                // if student_type is set then use that to determine new or old student
                if ($student->getMeta('student_type')) {
                    $isNewStudent = $student->getMeta('student_type') == 'new' ? true : false;
                } else {
                    $isNewStudent = $student->joining_date == $student->start_date->value || $student->getMeta('is_new', false) ? true : false;
                }

                $isOldStudent = ! $isNewStudent;

                (new UpdateFeeInstallment)->execute(
                    studentFee: $studentFee,
                    feeConcession: $feeConcession,
                    transportCircle: $transportCircle,
                    params: [
                        ...$fee,
                        'is_new_student' => $isNewStudent,
                        'is_old_student' => $isOldStudent,
                        'is_male_student' => $student->gender == Gender::MALE->value ? true : false,
                        'is_female_student' => $student->gender == Gender::FEMALE->value ? true : false,
                    ]
                );
            }
        }

        \DB::commit();
    }

    private function validateTransportChange(Fee $studentFee, ?Circle $transportCircle, array $params = []): void
    {
        if ($studentFee->paid->value <= 0) {
            return;
        }

        if (! $studentFee->transport_circle_id && $transportCircle) {
            return;
        }

        $transportFeeRecords = $studentFee->records->where('default_fee_head.value', 'transport_fee');
        $transportFeePaid = $transportFeeRecords->sum('paid.value');

        if ($studentFee->transport_circle_id && ! $transportCircle) {
            if ($transportFeePaid > 0) {
                throw ValidationException::withMessages(['message' => trans('student.fee.could_not_change_transport_if_paid')]);
            }
        } elseif ($studentFee->transport_circle_id != $transportCircle?->id || $studentFee->transport_direction != Arr::get($params, 'direction')) {
            // allow chaning fee if new fee is greater than  or equal to paid
            $newTransportFee = $studentFee->installment?->transportFee;
            $newTransportFeeRecord = $newTransportFee?->records->firstWhere('transport_circle_id', $transportCircle?->id);

            $newTransportFeeAmount = $newTransportFeeRecord?->roundtrip_amount?->value ?? 0;
            if (Arr::get($params, 'direction') == Direction::ARRIVAL->value) {
                $newTransportFeeAmount = $newTransportFeeRecord?->arrival_amount?->value ?? 0;
            } elseif (Arr::get($params, 'direction') == Direction::DEPARTURE->value) {
                $newTransportFeeAmount = $newTransportFeeRecord?->departure_amount?->value ?? 0;
            }

            if ($transportFeePaid > $newTransportFeeAmount) {
                throw ValidationException::withMessages(['message' => trans('student.fee.could_not_change_transport_if_paid_more', [
                    'paid' => \Price::from($transportFeePaid)->formatted,
                    'new' => \Price::from($newTransportFeeAmount)->formatted,
                ])]);
            }
        }
    }

    public function updateFeeInstallment(Request $request, Student $student, Fee $studentFee): void
    {
        throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);
        if ($studentFee->paid->value > 0) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_edit_if_fee_paid')]);
        }

        $feeConcession = $this->getFeeConcession($student, $request->fee_concession);

        \DB::beginTransaction();

        // (new UpdateFeeInstallment)->execute(
        //     studentFee: $studentFee,
        //     feeConcession: $feeConcession,
        //     params: [
        //         'transport_circle_id' => $request->transport_circle,
        //         ...$request->all(),
        //     ]
        // );

        \DB::commit();
    }

    public function resetFee(Student $student): void
    {
        if (! $student->isStudying()) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        if (! Fee::whereStudentId($student->id)->count()) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_reset_if_fee_not_set')]);
        }

        $student->load('fees');

        if ($student->fees->where('paid.value', '>', 0)->count()) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_reset_if_fee_paid')]);
        }

        \DB::beginTransaction();

        Fee::whereStudentId($student->id)->delete();

        $student->fee_structure_id = null;
        $student->save();

        \DB::commit();
    }

    public function getStudentFees(Student $student): array
    {
        return (new GetStudentFees)->execute($student);
    }

    public function setCustomConcession(Request $request, Student $student): void
    {
        $request->validate([
            'remarks' => 'required|min:2|max:200',
            'heads' => 'required|array',
            'heads.*.uuid' => 'required|uuid',
            'heads.*.concession_amount' => 'required|numeric|min:0',
            'apply_to_all_installments' => 'boolean',
        ], [], [
            'remarks' => trans('finance.fee.concession_remarks'),
            'heads' => trans('finance.fee_head.fee_head'),
            'heads.*.concession_amount' => trans('finance.fee.concession_amount'),
            'apply_to_all_installments' => trans('finance.fee_concession.apply_to_all_installments'),
        ]);

        $fees = Fee::query()
            ->with('records', 'installment', 'installment.group')
            ->whereStudentId($student->id)
            ->whereUuid($request->fee)
            ->get();

        $fee = $fees->first();

        // If multiple custom fee with same fee head is there, it will create issue to identify which one to apply the concession
        if ($fee->installment->group->getMeta('is_custom')) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_set_custom_concession_for_custom_fee')]);
        }

        $inputConcessions = [];
        foreach ($fee->records as $record) {
            $inputConcessionRecord = collect($request->heads)->firstWhere('uuid', $record->uuid);
            $feeDetail = $record->fee_head_id ?: $record->default_fee_head?->value;
            $inputConcessions[] = [
                'detail' => $feeDetail,
                'concession_amount' => Arr::get($inputConcessionRecord, 'concession_amount', 0),
            ];
        }

        if ($request->boolean('apply_to_all_installments')) {
            $feeGroup = $fee->installment->group;

            $fees = Fee::query()
                ->with('records')
                ->whereHas('installment', function ($q) use ($feeGroup) {
                    $q->whereFeeGroupId($feeGroup->id);
                })
                ->where('paid', '=', 0)
                ->whereStudentId($student->id)
                ->get();
        }

        foreach ($fees as $fee) {
            if ($fee->fee_concession_id) {
                throw ValidationException::withMessages(['message' => trans('student.fee.could_not_set_custom_concession_for_existing_concession')]);
            }

            if ($fee->total->value - $fee->paid->value <= 0) {
                throw ValidationException::withMessages(['message' => trans('student.fee.could_not_set_custom_concession_for_paid_fee')]);
            }
        }

        foreach ($fees as $fee) {
            foreach ($fee->records as $index => $record) {
                $inputConcession = collect($inputConcessions)->firstWhere('detail', $record->fee_head_id ?: $record->default_fee_head?->value);

                if (! $inputConcession) {
                    continue;
                }

                $balanceFee = $record->amount->value - $record->paid->value;
                $inputConcessionAmount = Arr::get($inputConcession, 'concession_amount', 0);

                if ($balanceFee < $inputConcessionAmount) {
                    throw ValidationException::withMessages(['heads.'.$index.'.concession_amount' => trans('student.fee.concession_amount_gt_balance', [
                        'balance' => \Price::from($balanceFee)->formatted,
                        'amount' => \Price::from($inputConcessionAmount)->formatted,
                        'installment' => $fee->installment->title,
                    ])]);
                }
            }
        }

        \DB::beginTransaction();

        foreach ($fees as $fee) {
            $totalConcessionAmount = 0;
            foreach ($fee->records as $index => $record) {
                $inputConcession = collect($inputConcessions)->firstWhere('detail', $record->fee_head_id ?: $record->default_fee_head?->value);

                if (! $inputConcession) {
                    continue;
                }

                $inputConcessionAmount = Arr::get($inputConcession, 'concession_amount', 0);

                $record->concession = $inputConcessionAmount;
                $record->save();

                $totalConcessionAmount += $inputConcessionAmount;
            }

            $fee->total->value = $fee->getInstallmentTotal()->value;
            $fee->setMeta([
                'has_custom_concession' => $totalConcessionAmount > 0 ? true : false,
                'custom_concession_remarks' => $request->remarks,
            ]);
            $fee->save();
        }

        \DB::commit();
    }
}
