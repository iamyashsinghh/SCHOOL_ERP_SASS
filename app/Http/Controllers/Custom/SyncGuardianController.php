<?php

namespace App\Http\Controllers\Custom;

use App\Actions\CreateContact;
use App\Enums\FamilyRelation;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;

class SyncGuardianController extends Controller
{
    public function __invoke(Request $request)
    {
        $students = Student::query()
            ->select('students.id', 'students.contact_id')
            ->get();

        $contacts = Contact::query()
            ->select('id')
            ->whereIn('id', $students->pluck('contact_id'))
            ->get();

        $fatherMissingContacts = Contact::query()
            ->select('id', 'father_name', 'contact_number')
            ->whereIn('id', $contacts->pluck('id'))
            ->whereDoesntHave('guardians', fn ($q) => $q->where('relation', FamilyRelation::FATHER)
            )
            ->get();

        $motherMissingContacts = Contact::query()
            ->select('id', 'mother_name', 'contact_number')
            ->whereIn('id', $contacts->pluck('id'))
            ->whereDoesntHave('guardians', fn ($q) => $q->where('relation', FamilyRelation::MOTHER)
            )
            ->get();

        if ($request->query('confirm') == 'yes') {
            foreach ($fatherMissingContacts->take(500) as $contact) {
                if (! $contact->father_name) {
                    continue;
                }

                $newFatherContact = (new CreateContact)->execute([
                    'name' => $contact->father_name,
                    'contact_number' => $contact->contact_number,
                    'relation' => FamilyRelation::FATHER->value,
                    'validate' => false,
                ]);

                $existingFatherGuardian = Guardian::query()
                    ->where('primary_contact_id', $contact->id)
                    ->where('relation', FamilyRelation::FATHER)
                    ->first();

                if ($existingFatherGuardian) {
                    continue;
                }

                Guardian::forceCreate([
                    'primary_contact_id' => $contact->id,
                    'contact_id' => $newFatherContact->id,
                    'relation' => FamilyRelation::FATHER,
                    'position' => 1,
                ]);
            }

            foreach ($motherMissingContacts->take(500) as $contact) {
                if (! $contact->mother_name) {
                    continue;
                }

                $newMotherContact = (new CreateContact)->execute([
                    'name' => $contact->mother_name,
                    'contact_number' => $contact->contact_number,
                    'relation' => FamilyRelation::MOTHER->value,
                    'validate' => false,
                ]);

                $existingMotherGuardian = Guardian::query()
                    ->where('primary_contact_id', $contact->id)
                    ->where('relation', FamilyRelation::MOTHER)
                    ->first();

                if ($existingMotherGuardian) {
                    continue;
                }

                Guardian::forceCreate([
                    'primary_contact_id' => $contact->id,
                    'contact_id' => $newMotherContact->id,
                    'relation' => FamilyRelation::MOTHER,
                    'position' => 0,
                ]);
            }

            return redirect()->route('custom.sync-guardian');
        }

        return view('custom.sync-guardian', [
            'fatherMissing' => $fatherMissingContacts->count(),
            'motherMissing' => $motherMissingContacts->count(),
        ]);
    }
}
