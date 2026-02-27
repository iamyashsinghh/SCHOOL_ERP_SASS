<?php

namespace App\Actions\Student;

use App\Models\Academic\Batch;
use App\Models\Finance\FeeAllocation;
use App\Models\Student\Student;
use Illuminate\Support\Arr;

class ChangeCourse
{
    public function execute(Student $student, array $params): void
    {
        $newBatchId = Arr::get($params, 'new_batch_id');

        $batch = Batch::query()
            ->findOrFail($newBatchId);

        $feeAllocation = FeeAllocation::query()
            ->whereBatchId($batch->id)
            ->first() ?? FeeAllocation::query()
            ->whereCourseId($batch->course_id)
            ->first();

        $feeAllocation->load(
            'structure.installments.records',
            'structure.installments.transportFee.records',
        );

        $feeStructure = $feeAllocation->structure;

        // check if the fee can be transferred or not

        // check if headwise total paid amount is available in new course

        // update course
    }
}
