<?php

namespace App\Services\Academic;

use App\Http\Resources\Academic\CourseResource;
use App\Models\Academic\Batch;
use App\Models\Academic\Course;
use App\Models\Finance\FeeAllocation;
use App\Models\Finance\FeeInstallmentRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BatchService
{
    public function preRequisite(Request $request)
    {
        $courses = CourseResource::collection(Course::query()
            ->byPeriod()
            ->get());

        return compact('courses');
    }

    public function getOptionalFeeHeads(Batch $batch): array
    {
        $batchFeeAllocation = FeeAllocation::query()
            ->whereBatchId($batch->id)
            ->first() ?? FeeAllocation::query()
            ->whereCourseId($batch->course_id)
            ->first();

        if (! $batchFeeAllocation) {
            return [];
        }

        $feeInstallmentRecords = FeeInstallmentRecord::query()
            ->with('head')
            ->whereHas('installment', function ($q) use ($batchFeeAllocation) {
                $q->whereFeeStructureId($batchFeeAllocation->fee_structure_id);
            })
            ->whereIsOptional(1)
            ->get();

        $optionalFeeHeads = $feeInstallmentRecords->map(function ($feeInstallmentRecord) {
            return [
                'name' => $feeInstallmentRecord->head->name,
                'uuid' => $feeInstallmentRecord->head->uuid,
            ];
        })->unique('uuid')->values()->all();

        return $optionalFeeHeads;
    }

    public function create(Request $request): Batch
    {
        \DB::beginTransaction();

        $batch = Batch::forceCreate($this->formatParams($request));

        \DB::commit();

        return $batch;
    }

    private function formatParams(Request $request, ?Batch $batch = null): array
    {
        $formatted = [
            'name' => $request->name,
            'course_id' => $request->course_id,
            'max_strength' => ! empty($request->max_strength) ? $request->max_strength : null,
            'description' => $request->description,
        ];

        $meta = $batch?->meta ?? [];

        $meta['pg_account'] = $request->pg_account;

        $config = $batch?->config ?? [];
        $config['roll_number_prefix'] = $request->roll_number_prefix;
        $formatted['config'] = $config;

        if (! $batch) {
            $formatted['position'] = $request->integer('position', 0);
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Batch $batch): void
    {
        \DB::beginTransaction();

        $batch->forceFill($this->formatParams($request, $batch))->save();

        \DB::commit();
    }

    public function deletable(Batch $batch): bool
    {
        $subjectRecordExists = \DB::table('subject_records')
            ->whereBatchId($batch->id)
            ->exists();

        if ($subjectRecordExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('academic.batch.batch'), 'dependency' => trans('academic.subject.subject')])]);
        }

        $feeAllocationExists = \DB::table('fee_allocations')
            ->whereBatchId($batch->id)
            ->exists();

        if ($feeAllocationExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('academic.batch.batch'), 'dependency' => trans('finance.fee_structure.allocation')])]);
        }

        $admissionExists = \DB::table('admissions')
            ->whereBatchId($batch->id)
            ->exists();

        if ($admissionExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('academic.batch.batch'), 'dependency' => trans('student.admission.admission')])]);
        }

        $studentExists = \DB::table('students')
            ->whereBatchId($batch->id)
            ->exists();

        if ($studentExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('academic.batch.batch'), 'dependency' => trans('student.student')])]);
        }

        $examScheduleExists = \DB::table('exam_schedules')
            ->whereBatchId($batch->id)
            ->exists();

        if ($examScheduleExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('academic.batch.batch'), 'dependency' => trans('exam.schedule.schedule')])]);
        }

        return true;
    }
}
