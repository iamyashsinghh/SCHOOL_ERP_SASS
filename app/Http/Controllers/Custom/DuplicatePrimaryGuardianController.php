<?php

namespace App\Http\Controllers\Custom;

use App\Enums\FamilyRelation;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student\Student;
use App\Services\Student\GuardianActionService;
use Illuminate\Http\Request;

class DuplicatePrimaryGuardianController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->query('student') && $request->query('guardian')) {
            $student = Student::query()
                ->whereUuid($request->query('student'))
                ->first();

            if ($student) {
                (new GuardianActionService)->makePrimary($request, $student, $request->query('guardian'));

                return redirect()->route('custom.duplicate-primary-guardian');
            }
        }

        $students = Guardian::query()
            ->select('students.id', 'students.uuid', 'students.contact_id', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'), 'admissions.code_number')
            ->join('contacts', 'contacts.id', '=', 'guardians.primary_contact_id')
            ->join('students', 'students.contact_id', '=', 'contacts.id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->where('contacts.team_id', auth()->user()->current_team_id)
            ->where('guardians.position', 1)
            ->groupBy('students.id')
            ->havingRaw('COUNT(guardians.id) > 1')
            ->get();

        $guardians = Guardian::query()
            ->with('contact')
            ->whereIn('primary_contact_id', $students->pluck('contact_id'))
            ->where('position', 1)
            ->get();

        $students = $students->map(function ($student) use ($guardians) {
            $studentGuardians = $guardians->where('primary_contact_id', $student->contact_id);

            return [
                'id' => $student->id,
                'uuid' => $student->uuid,
                'contact_id' => $student->contact_id,
                'name' => $student->name,
                'code_number' => $student->code_number,
                'guardians' => $studentGuardians->map(function ($guardian) {
                    return [
                        'id' => $guardian->id,
                        'uuid' => $guardian->uuid,
                        'name' => $guardian->contact->name,
                        'relation' => FamilyRelation::getDetail($guardian->relation),
                        'position' => $guardian->position,
                    ];
                }),
            ];
        });

        return view('custom.duplicate-primary-guardian', [
            'students' => $students,
        ]);
    }
}
