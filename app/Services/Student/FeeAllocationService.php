<?php

namespace App\Services\Student;

use App\Actions\Student\AssignFee;
use App\Actions\Student\BulkUpdateFeeInstallment;
use App\Actions\Student\CalculateFeeConcession;
use App\Actions\Student\FetchBatchWiseStudent;
use App\Actions\Student\GetTransportConcessionFeeAmount;
use App\Actions\Student\GetTransportFeeAmount;
use App\Enums\Finance\DefaultFeeHead;
use App\Enums\Gender;
use App\Enums\OptionType;
use App\Enums\Student\StudentType;
use App\Enums\Transport\Direction;
use App\Http\Resources\Finance\FeeConcessionResource;
use App\Http\Resources\Finance\FeeHeadResource;
use App\Http\Resources\Finance\FeeStructureResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\Student\FeeAllocationResource;
use App\Http\Resources\Transport\CircleResource;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Finance\FeeAllocation;
use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Finance\FeeHead;
use App\Models\Tenant\Finance\FeeStructure;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Transport\Circle;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeeAllocationService
{
    public function preRequisite(Request $request)
    {
        $directions = Direction::getOptions();

        $transportCircles = CircleResource::collection(Circle::query()
            ->byPeriod()
            ->get());

        $feeStructures = FeeStructureResource::collection(FeeStructure::query()
            ->byPeriod()
            ->get());

        $feeConcessions = FeeConcessionResource::collection(FeeConcession::query()
            ->byPeriod()
            ->get());

        $feeConcessionTypes = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::FEE_CONCESSION_TYPE->value)
            ->get());

        $feeHeads = FeeHeadResource::collection(FeeHead::query()
            ->byPeriod()
            ->whereHas('group', function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('meta->is_custom')->orWhere('meta->is_custom', false);
                });
            })
            ->get());

        $groups = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_GROUP->value)
            ->get());

        $studentTypes = StudentType::getOptions();

        return compact('directions', 'transportCircles', 'feeStructures', 'feeConcessions', 'feeConcessionTypes', 'feeHeads', 'groups', 'studentTypes');
    }

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'codeNumber',
                'label' => trans('student.admission.props.code_number'),
                'print_label' => 'code_number',
                'print_sub_label' => 'joining_date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'name',
                'label' => trans('contact.props.name'),
                'print_label' => 'name',
                'print_label' => 'gender.label',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'course',
                'label' => trans('academic.course.course'),
                'print_label' => 'course_name + batch_name',
                // 'print_sub_label' => 'batch_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'installmentCount',
                'label' => trans('finance.fee_structure.installment_count'),
                'print_label' => 'fee_structure_name',
                'print_sub_label' => 'fees_count',
                'print_additional_label' => 'fee_concession_type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'transport',
                'label' => trans('transport.transport'),
                'print_label' => 'transport_circle_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'feeConcession',
                'label' => trans('finance.fee_concession.fee_concession'),
                'print_label' => 'fee_concession_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'contactNumber',
                'label' => trans('contact.props.contact_number'),
                'print_label' => 'contact_number',
                'print_sub_label' => 'email',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'parent',
                'label' => trans('student.props.parent'),
                'print_label' => 'father_name',
                'print_sub_label' => 'mother_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'address',
                'label' => trans('contact.props.address.address'),
                'print_label' => 'short_address',
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        if (request()->ajax()) {
            array_unshift($headers, ['key' => 'selectAll', 'sortable' => false]);
        }

        return $headers;
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'action' => 'required|in:assign,update',
        ]);

        $request->merge([
            'paginate' => true,
            'fees_count' => true,
            'with_fee_concession_type' => true,
        ]);

        $action = $request->input('action', 'assign');
        $params = $request->all();

        $params['fee_structure_type'] = 'all';

        // Action wise filter
        // if ($action == 'assign') {
        //     $params['fee_structure_type'] = 'without';
        // } else if ($action == 'update') {
        //     $params['fee_structure_type'] = 'with';
        // }

        // $params['fee_structure_uuid'] = $request->existing_fee_structure;

        if (Str::isUuid($request->existing_fee_structure)) {
            $params['fee_structure_uuid'] = $request->existing_fee_structure;
        } elseif ($request->existing_fee_structure) {
            $params['fee_structure_type'] = 'without';
        } else {
            $params['fee_structure_type'] = 'all';
        }

        $students = (new FetchBatchWiseStudent)->execute($params);

        $batch = Batch::query()
            ->byPeriod()
            ->where('uuid', $request->batch)
            ->firstOrFail();

        $batchFeeAllocation = FeeAllocation::query()
            ->whereBatchId($batch->id)
            ->first() ?? FeeAllocation::query()
            ->whereCourseId($batch->course_id)
            ->first();

        $feeStructureIds = $students->pluck('fee_structure_id')->filter()->unique()->toArray();

        $studentIds = $students->pluck('id')->toArray();

        $studentFees = Fee::query()
            ->select('student_fees.id', 'student_fees.student_id', 'transport_circles.name as transport_circle_name', 'fee_concessions.name as fee_concession_name')
            ->join('fee_installments', 'student_fees.fee_installment_id', '=', 'fee_installments.id')
            ->leftJoin('transport_circles', 'student_fees.transport_circle_id', '=', 'transport_circles.id')
            ->leftJoin('fee_concessions', 'student_fees.fee_concession_id', '=', 'fee_concessions.id')
            ->whereIn('student_fees.student_id', $studentIds)
            ->selectRaw('
                COALESCE(student_fees.due_date, fee_installments.due_date) as effective_due_date
            ')
            ->orderByRaw('effective_due_date desc, student_fees.student_id desc')
            ->get();

        $feeStructures = FeeStructure::query()
            ->byPeriod()
            ->whereIn('id', $feeStructureIds)
            ->get();

        $request->merge([
            'fee_structures' => $feeStructures,
            'student_fees' => $studentFees,
        ]);

        return FeeAllocationResource::collection($students)
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => ['name'],
                    'default_sort' => 'name',
                    'default_order' => 'asc',
                    'has_batch_fee_allocation' => $batchFeeAllocation ? true : false,
                ],
            ]);
    }

    public function allocate(Request $request)
    {
        $selectAll = $request->boolean('select_all');

        $params = $request->all();
        $action = $request->input('action', 'assign');

        $params['fee_structure_type'] = 'all';
        $params['fee_structure_uuid'] = null;

        // Action wise filter
        // if ($action == 'assign') {
        //     $params['fee_structure_type'] = 'without';
        // } else if ($action == 'update') {
        //     $params['fee_structure_type'] = 'with';
        // }

        // $params['fee_structure_uuid'] = $request->existing_fee_structure;

        if (Str::isUuid($request->existing_fee_structure)) {
            $params['fee_structure_uuid'] = $request->existing_fee_structure;
        } elseif ($request->existing_fee_structure) {
            $params['fee_structure_type'] = 'without';
        } else {
            $params['fee_structure_type'] = 'all';
        }

        if ($selectAll) {
            $students = (new FetchBatchWiseStudent)->execute($params, true);
        } else {
            $students = (new FetchBatchWiseStudent)->execute($params, true);

            if (array_diff($request->students, Arr::pluck($students, 'uuid'))) {
                throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
            }
        }

        $batch = Batch::query()
            ->byPeriod()
            ->where('uuid', $request->batch)
            ->firstOrFail();

        if (Arr::get($params, 'fee_structure_uuid')) {
            $this->updateFeeAllocation($request, $students, $params);

            return;
        }

        if (collect($students)->where('fee_structure_id')->isNotEmpty()) {
            throw ValidationException::withMessages(['message' => trans('student.fee.already_allocated')]);
        }

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

        $feeAllocationBatch = Str::random(10);

        \DB::beginTransaction();

        foreach ($students as $student) {
            $student = Student::query()
                ->select('students.*', 'contacts.gender', 'admissions.joining_date')
                ->join('contacts', 'students.contact_id', '=', 'contacts.id')
                ->join('admissions', 'students.admission_id', '=', 'admissions.id')
                ->where('students.uuid', Arr::get($student, 'uuid'))
                ->first();

            $isNewStudent = false;
            if ($student->joining_date == $student->start_date->value || $student->getMeta('is_new', false)) {
                $isNewStudent = true;
            }

            // if request has student_type then use that to determine new or old student
            if ($request->student_type == 'new') {
                $isNewStudent = true;
            } elseif ($request->student_type == 'old') {
                $isNewStudent = false;
            }

            $isMaleStudent = false;
            $isFemaleStudent = false;

            if ($student->gender == Gender::MALE->value) {
                $isMaleStudent = true;
            } elseif ($student->gender == Gender::FEMALE->value) {
                $isFemaleStudent = true;
            }

            if ($student->fee_structure_id) {
                continue;
            }

            $student->setMeta([
                'student_type' => $request->student_type,
            ]);
            $student->save();

            (new AssignFee)->execute(
                student: $student,
                feeConcession: $request->fee_concession,
                transportCircle: $request->transport_circle,
                feeStructure: $feeStructure,
                params: [
                    'direction' => $request->direction,
                    'opted_fee_heads' => $request->opted_fee_heads,
                    'fee_allocation_batch' => $feeAllocationBatch,
                    'is_new_student' => $isNewStudent,
                    'is_male_student' => $isMaleStudent,
                    'is_female_student' => $isFemaleStudent,
                ]
            );
        }

        \DB::commit();
    }

    private function updateFeeAllocation(Request $request, array $students, array $params = [])
    {
        $optedFeeHeads = Arr::get($params, 'opted_fee_heads', []) ? FeeHead::query()
            ->select('id')
            ->byPeriod()
            ->whereIn('uuid', Arr::get($params, 'opted_fee_heads', []))
            ->get()
            ->pluck('id')
            ->toArray() : [];

        $feeConcessions = FeeConcession::query()
            ->with('records')
            ->byPeriod()
            ->get();

        $feeStructureIds = collect($students)->pluck('fee_structure_id')->filter()->unique()->toArray();

        if (count($feeStructureIds) > 1) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_update_if_multiple_fee_structures')]);
        }

        $transportCircle = $request->transport_circle;

        \DB::beginTransaction();

        foreach ($students as $student) {
            $student = Student::query()
                ->with('fees.installment.records', 'fees.records', 'fees.payments')
                ->select('students.*', 'contacts.gender', 'admissions.joining_date')
                ->join('contacts', 'students.contact_id', '=', 'contacts.id')
                ->join('admissions', 'students.admission_id', '=', 'admissions.id')
                ->where('students.uuid', Arr::get($student, 'uuid'))
                ->first();

            $isNewStudent = false;
            if ($student->joining_date == $student->start_date->value) {
                $isNewStudent = true;
            }

            $isMaleStudent = false;
            $isFemaleStudent = false;

            if ($student->gender == Gender::MALE->value) {
                $isMaleStudent = true;
            } elseif ($student->gender == Gender::FEMALE->value) {
                $isFemaleStudent = true;
            }

            $student->load('fees.installment.records', 'fees.records');

            foreach ($student->fees as $studentFee) {
                (new BulkUpdateFeeInstallment)->execute(
                    studentFee: $studentFee,
                    feeConcessions: $feeConcessions,
                    transportCircle: $transportCircle,
                    params: [
                        'direction' => $request->direction,
                        'opted_fee_heads' => $optedFeeHeads,
                        'is_new_student' => $isNewStudent,
                        'is_male_student' => $isMaleStudent,
                        'is_female_student' => $isFemaleStudent,
                    ]
                );
            }
        }

        \DB::commit();
    }

    public function allocateFeeConcession(Request $request)
    {
        if (! $request->fee_concession && ! $request->fee_concession_type) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $feeConcession = $request->fee_concession;

        if (! auth()->user()->is_default) {
            $request->merge(['remove_concession' => false]);
        }

        $selectAll = $request->boolean('select_all');

        if ($selectAll) {
            $students = (new FetchBatchWiseStudent)->execute($request->all(), true);
        } else {
            $students = (new FetchBatchWiseStudent)->execute($request->all(), true);

            if (array_diff($request->students, Arr::pluck($students, 'uuid'))) {
                throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
            }
        }

        $students = Student::query()
            ->with('fees.installment.records', 'fees.records')
            ->whereNotNull('fee_structure_id')
            ->whereIn('students.uuid', Arr::pluck($students, 'uuid'))
            ->get();

        \DB::beginTransaction();

        foreach ($students as $student) {
            if ($feeConcession) {
                foreach ($student->fees as $studentFee) {
                    if ($request->boolean('remove_concession')) {
                        $this->removeConcession($studentFee);
                    } else {
                        $this->updateConcession($studentFee, $feeConcession);
                    }

                    $studentFee->refresh();
                    $studentFee->total = $studentFee->getInstallmentTotal()->value;
                    $studentFee->save();
                }
            }

            $student->fee_concession_type_id = $request->fee_concession_type?->id;
            $student->save();
        }

        \DB::commit();
    }

    private function updateConcession(Fee $studentFee, ?FeeConcession $feeConcession): void
    {
        if (! $feeConcession) {
            return;
        }

        if ($studentFee->getMeta('has_custom_concession')) {
            return;
        }

        $feeInstallment = $studentFee->installment;

        if ($feeInstallment->getMeta('has_no_concession')) {
            return;
        }

        if (! auth()->user()->is_default && $studentFee->fee_concession_id == $feeConcession->id) {
            return;
        }

        if ($studentFee->paid->value > 0) {
            return;
        }

        $studentFee->fee_concession_id = $feeConcession->id;
        $studentFee->save();

        foreach ($feeInstallment->records as $feeInstallmentRecord) {
            $feeHeadId = $feeInstallmentRecord->fee_head_id;

            $studentFeeRecord = $studentFee->records->firstWhere('fee_head_id', $feeHeadId);

            if (! $studentFeeRecord) {
                continue;
            }

            $amount = $studentFeeRecord->amount->value;

            $concessionAmount = (new CalculateFeeConcession)->execute(
                feeConcession: $feeConcession,
                feeHeadId: $feeHeadId,
                amount: $amount
            );

            $studentFeeRecord->concession = $concessionAmount;
            $studentFeeRecord->save();
        }

        if (! $feeInstallment->transport_fee_id) {
            return;
        }

        $studentFeeRecord = $studentFee->records->firstWhere('default_fee_head.value', DefaultFeeHead::TRANSPORT_FEE->value);

        if (! $studentFeeRecord) {
            return;
        }

        $transportFeeAmount = (new GetTransportFeeAmount)->execute(
            studentFee: $studentFee,
            feeInstallment: $feeInstallment
        );

        $transportFeeConcessionAmount = (new GetTransportConcessionFeeAmount)->execute(
            feeConcession: $feeConcession,
            transportFeeAmount: $transportFeeAmount
        );

        $studentFeeRecord->amount = $transportFeeAmount;
        $studentFeeRecord->concession = $transportFeeConcessionAmount;
        $studentFeeRecord->save();
    }

    private function removeConcession(Fee $studentFee): void
    {
        $feeInstallment = $studentFee->installment;

        if ($studentFee->paid->value > 0) {
            return;
        }

        $studentFee->fee_concession_id = null;
        $studentFee->save();

        foreach ($feeInstallment->records as $feeInstallmentRecord) {
            $feeHeadId = $feeInstallmentRecord->fee_head_id;

            $studentFeeRecord = $studentFee->records->firstWhere('fee_head_id', $feeHeadId);

            if (! $studentFeeRecord) {
                continue;
            }

            $amount = $studentFeeRecord->amount->value;
            $studentFeeRecord->concession = 0;
            $studentFeeRecord->save();
        }

        if (! $feeInstallment->transport_fee_id) {
            return;
        }

        $studentFeeRecord = $studentFee->records->firstWhere('default_fee_head.value', DefaultFeeHead::TRANSPORT_FEE->value);

        if (! $studentFeeRecord) {
            return;
        }

        $transportFeeAmount = (new GetTransportFeeAmount)->execute(
            studentFee: $studentFee,
            feeInstallment: $feeInstallment
        );

        $studentFeeRecord->amount = $transportFeeAmount;
        $studentFeeRecord->concession = 0;
        $studentFeeRecord->save();
    }

    public function remove(Request $request)
    {
        $selectAll = $request->boolean('select_all');

        if ($selectAll) {
            $students = (new FetchBatchWiseStudent)->execute($request->all(), true);
        } else {
            $students = (new FetchBatchWiseStudent)->execute($request->all(), true);

            if (array_diff($request->students, Arr::pluck($students, 'uuid'))) {
                throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
            }
        }

        $students = Student::query()
            ->whereNotNull('fee_structure_id')
            ->whereIn('uuid', $request->students)
            ->get();

        if (! $students->count()) {
            throw ValidationException::withMessages(['message' => trans('student.fee.no_allocation_found')]);
        }

        $students->load('fees');

        \DB::beginTransaction();

        $removeCount = 0;
        foreach ($students as $student) {
            $paidFee = $student->fees->sum('paid.value');

            if ($paidFee) {
                continue;
            }

            Fee::whereStudentId($student->id)->delete();

            $student->fee_structure_id = null;
            $student->save();
            $removeCount++;
        }

        \DB::commit();

        if (! $removeCount) {
            throw ValidationException::withMessages(['message' => trans('student.fee.could_not_remove_paid_allocation')]);
        }
    }
}
