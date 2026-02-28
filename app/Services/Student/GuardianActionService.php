<?php

namespace App\Services\Student;

use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;

class GuardianActionService
{
    public function makePrimary(Request $request, Student $student, string $guardian): void
    {
        $guardian = Guardian::query()
            ->wherePrimaryContactId($student->contact_id)
            ->whereUuid($guardian)
            ->getOrFail(trans('guardian.guardian'), 'guardian');

        Guardian::query()
            ->where('primary_contact_id', $student->contact_id)
            ->where('id', '!=', $guardian->id)
            ->update([
                'position' => 0,
            ]);

        $guardian->position = 1;
        $guardian->save();
    }
}
