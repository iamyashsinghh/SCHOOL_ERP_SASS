<?php

namespace App\Services\Student;

use App\Actions\Student\FetchBatchWiseStudent;
use App\Enums\FamilyRelation;
use App\Http\Resources\Student\StudentResource;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Guardian;
use Illuminate\Http\Request;

class PhotoService
{
    public function preRequisite(Request $request)
    {
        $batches = Batch::getList();

        $sortBy = [
            ['label' => trans('student.props.name'), 'value' => 'name'],
            ['label' => trans('student.roll_number.roll_number'), 'value' => 'roll_number'],
            ['label' => trans('student.admission.props.date'), 'value' => 'admission_date'],
            ['label' => trans('student.admission.props.code_number'), 'value' => 'code_number'],
        ];

        $orderBy = [
            ['label' => trans('list.orders.asc'), 'value' => 'asc'],
            ['label' => trans('list.orders.desc'), 'value' => 'desc'],
        ];

        return compact('batches', 'sortBy', 'orderBy');
    }

    private function validateInput(Request $request): Batch
    {
        return Batch::query()
            ->byPeriod()
            ->filterAccessible()
            ->whereUuid($request->batch)
            ->getOrFail(trans('academic.batch.batch'), 'batch');
    }

    public function fetch(Request $request)
    {
        $batch = $this->validateInput($request);

        $request->merge(['select_all' => true]);

        $students = (new FetchBatchWiseStudent)->execute($request->all());

        $guardians = Guardian::query()
            ->with('contact')
            ->whereIn('primary_contact_id', $students->pluck('contact_id'))
            ->whereIn('relation', [
                FamilyRelation::FATHER->value,
                FamilyRelation::MOTHER->value,
            ])
            ->get();

        $students = $students->map(function ($student) use ($guardians) {
            $fatherGuardian = $guardians->where('relation', FamilyRelation::FATHER)->firstWhere('primary_contact_id', $student->contact_id);
            $motherGuardian = $guardians->where('relation', FamilyRelation::MOTHER)->firstWhere('primary_contact_id', $student->contact_id);

            $student->father_guardian_uuid = $fatherGuardian?->uuid;
            $student->father_photo = $fatherGuardian ? $fatherGuardian?->contact?->photo_url : (new Contact)->photo_url;
            $student->mother_guardian_uuid = $motherGuardian?->uuid;
            $student->mother_photo = $motherGuardian ? $motherGuardian?->contact?->photo_url : (new Contact)->photo_url;

            return $student;
        });

        $request->merge(['with_guardian_photo' => true]);

        return StudentResource::collection($students)
            ->additional([
                'meta' => [],
            ]);
    }
}
