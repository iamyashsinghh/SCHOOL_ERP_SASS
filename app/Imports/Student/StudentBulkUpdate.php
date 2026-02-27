<?php

namespace App\Imports\Student;

use App\Concerns\ItemImport;
use App\Enums\BloodGroup;
use App\Enums\MaritalStatus;
use App\Enums\OptionType;
use App\Helpers\SysHelper;
use App\Models\Contact;
use App\Models\Option;
use App\Models\Student\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\HeadingRowImport;

class StudentBulkUpdate implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        $headings = (new HeadingRowImport)->toArray(request()->file('file'))[0][0];

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('student');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('student', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        activity()->disableLogging();

        \DB::beginTransaction();

        $contacts = Contact::query()
            ->byTeam()
            ->whereIn('id', $rows->pluck('contact_id'))
            ->get();

        $categories = Option::query()
            ->byTeam()
            ->whereType(OptionType::MEMBER_CATEGORY->value)
            ->get();

        $castes = Option::query()
            ->byTeam()
            ->whereType(OptionType::MEMBER_CASTE->value)
            ->get();

        $religions = Option::query()
            ->byTeam()
            ->whereType(OptionType::RELIGION->value)
            ->get();

        foreach ($rows as $row) {
            $category = $categories->firstWhere('name', trim(Arr::get($row, 'category')));
            $caste = $castes->firstWhere('name', Arr::get($row, 'caste'));
            $religion = $religions->firstWhere('name', Arr::get($row, 'religion'));

            $bloodGroup = BloodGroup::tryFromAliases(Arr::get($row, 'blood_group'))?->value;
            $maritalStatus = MaritalStatus::tryFrom(strtolower(Arr::get($row, 'marital_status')))?->value;

            $contact = $contacts->firstWhere('id', Arr::get($row, 'contact_id'));

            $contact->update([
                'blood_group' => Arr::get($row, 'blood_group') ? $bloodGroup : $contact->blood_group,
                'category_id' => Arr::get($row, 'category') ? $category?->id : $contact->category_id,
                'caste_id' => Arr::get($row, 'caste') ? $caste?->id : $contact->caste_id,
                'religion_id' => Arr::get($row, 'religion') ? $religion?->id : $contact->religion_id,
                'unique_id_number1' => Arr::get($row, 'unique_id1') ? SysHelper::cleanInput(Arr::get($row, 'unique_id1')) : $contact->unique_id_number1,
                'unique_id_number2' => Arr::get($row, 'unique_id2') ? SysHelper::cleanInput(Arr::get($row, 'unique_id2')) : $contact->unique_id_number2,
                'unique_id_number3' => Arr::get($row, 'unique_id3') ? SysHelper::cleanInput(Arr::get($row, 'unique_id3')) : $contact->unique_id_number3,
                'unique_id_number4' => Arr::get($row, 'unique_id4') ? SysHelper::cleanInput(Arr::get($row, 'unique_id4')) : $contact->unique_id_number4,
                'unique_id_number5' => Arr::get($row, 'unique_id5') ? SysHelper::cleanInput(Arr::get($row, 'unique_id5')) : $contact->unique_id_number5,
                'nationality' => Arr::get($row, 'nationality') ? SysHelper::cleanInput(Arr::get($row, 'nationality')) : $contact->nationality,
                'mother_tongue' => Arr::get($row, 'mother_tongue') ? SysHelper::cleanInput(Arr::get($row, 'mother_tongue')) : $contact->mother_tongue,
                'birth_place' => Arr::get($row, 'birth_place') ? SysHelper::cleanInput(Arr::get($row, 'birth_place')) : $contact->birth_place,
                'alternate_records' => [
                    'contact_number' => Arr::get($row, 'alternate_contact_number') ? SysHelper::cleanInput(Arr::get($row, 'alternate_contact_number')) : Arr::get($contact->alternate_records, 'contact_number'),
                    'email' => Arr::get($row, 'alternate_email') ? SysHelper::cleanInput(Arr::get($row, 'alternate_email')) : Arr::get($contact->alternate_records, 'email'),
                ],
                'emergency_contact_records' => [
                    'name' => Arr::get($row, 'emergency_contact_name') ? SysHelper::cleanInput(Arr::get($row, 'emergency_contact_name')) : Arr::get($contact->emergency_contact_records, 'name'),
                    'contact_number' => Arr::get($row, 'emergency_contact_number') ? SysHelper::cleanInput(Arr::get($row, 'emergency_contact_number')) : Arr::get($contact->emergency_contact_records, 'contact_number'),
                    'relation' => Arr::get($row, 'emergency_contact_relation') ? SysHelper::cleanInput(Arr::get($row, 'emergency_contact_relation')) : Arr::get($contact->emergency_contact_records, 'relation'),
                ],
                'address' => [
                    'present' => [
                        'address_line1' => Arr::get($row, 'address_line1') ? SysHelper::cleanInput(Arr::get($row, 'address_line1')) : Arr::get($contact->address, 'present.address_line1'),
                        'address_line2' => Arr::get($row, 'address_line2') ? SysHelper::cleanInput(Arr::get($row, 'address_line2')) : Arr::get($contact->address, 'present.address_line2'),
                        'city' => Arr::get($row, 'city') ? SysHelper::cleanInput(Arr::get($row, 'city')) : Arr::get($contact->address, 'present.city'),
                        'state' => Arr::get($row, 'state') ? SysHelper::cleanInput(Arr::get($row, 'state')) : Arr::get($contact->address, 'present.state'),
                        'zipcode' => Arr::get($row, 'zipcode') ? SysHelper::cleanInput(Arr::get($row, 'zipcode')) : Arr::get($contact->address, 'present.zipcode'),
                        'country' => Arr::get($row, 'country') ? SysHelper::cleanInput(Arr::get($row, 'country')) : Arr::get($contact->address, 'present.country'),
                    ],
                ],
            ]);
        }

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $categories = Option::query()
            ->byTeam()
            ->whereType(OptionType::MEMBER_CATEGORY->value)
            ->get()
            ->pluck('name')
            ->all();

        $castes = Option::query()
            ->byTeam()
            ->whereType(OptionType::MEMBER_CASTE->value)
            ->get()
            ->pluck('name')
            ->all();

        $religions = Option::query()
            ->byTeam()
            ->whereType(OptionType::RELIGION->value)
            ->get()
            ->pluck('name')
            ->all();

        $students = Student::query()
            ->summary()
            ->get();

        $errors = [];

        $newUniqueIdNumber1s = [];
        $newUniqueIdNumber2s = [];
        $newUniqueIdNumber3s = [];
        $newUniqueIdNumber4s = [];
        $newUniqueIdNumber5s = [];

        $newRows = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'student');
            $alternateContactNumber = Arr::get($row, 'alternate_contact_number');
            $alternateEmail = Arr::get($row, 'alternate_email');
            $emergencyContactName = Arr::get($row, 'emergency_contact_name');
            $emergencyContactNumber = Arr::get($row, 'emergency_contact_number');
            $emergencyContactRelation = Arr::get($row, 'emergency_contact_relation');

            $bloodGroup = Arr::get($row, 'blood_group');
            $maritalStatus = Arr::get($row, 'marital_status');
            $category = trim(Arr::get($row, 'category'));
            $caste = Arr::get($row, 'caste');
            $religion = Arr::get($row, 'religion');

            $address = Arr::get($row, 'address');
            $addressLine1 = Arr::get($row, 'address_line1');
            $addressLine2 = Arr::get($row, 'address_line2');
            $city = Arr::get($row, 'city');
            $state = Arr::get($row, 'state');
            $zipcode = Arr::get($row, 'zipcode');
            $country = Arr::get($row, 'country');

            $password = Arr::get($row, 'password');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'required');
            } elseif (! $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'invalid');
            }

            if ($address && strlen($address) > 250) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.address'), 'max', ['max' => 100]);
            }

            if ($addressLine1 && strlen($addressLine1) > 250) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.address_line1'), 'max', ['max' => 100]);
            }

            if ($addressLine2 && strlen($addressLine2) > 100) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.address_line2'), 'max', ['max' => 100]);
            }

            if ($city && strlen($city) > 50) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.city'), 'max', ['max' => 50]);
            }

            if ($state && strlen($state) > 50) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.state'), 'max', ['max' => 50]);
            }

            if ($zipcode && strlen($zipcode) > 10) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.zipcode'), 'max', ['max' => 10]);
            }

            if ($country && strlen($country) > 20) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.country'), 'max', ['max' => 20]);
            }

            if ($alternateContactNumber && strlen($alternateContactNumber) > 20) {
                $errors[] = $this->setError($rowNo, trans('contact.props.alternate_contact_number'), 'max', ['max' => 20]);
            }

            if ($alternateEmail && ! filter_var($alternateEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.alternate_email'), 'invalid');
            }

            if ($emergencyContactName && strlen($emergencyContactName) > 100) {
                $errors[] = $this->setError($rowNo, trans('contact.props.emergency_contact_name'), 'max', ['max' => 100]);
            }

            if ($emergencyContactNumber && strlen($emergencyContactNumber) > 20) {
                $errors[] = $this->setError($rowNo, trans('contact.props.emergency_contact_number'), 'max', ['max' => 20]);
            }

            if ($emergencyContactRelation && strlen($emergencyContactRelation) > 20) {
                $errors[] = $this->setError($rowNo, trans('contact.props.emergency_contact_relation'), 'max', ['max' => 20]);
            }

            if ($bloodGroup && ! in_array(strtolower($bloodGroup), BloodGroup::getKeysWithAlias())) {
                $errors[] = $this->setError($rowNo, trans('contact.props.blood_group'), 'invalid');
            }

            if ($maritalStatus && ! in_array(strtolower($maritalStatus), MaritalStatus::getKeys())) {
                $errors[] = $this->setError($rowNo, trans('contact.props.marital_status'), 'invalid');
            }

            if ($category && ! in_array($category, $categories)) {
                $errors[] = $this->setError($rowNo, trans('contact.category.category'), 'invalid');
            }

            if ($caste && ! in_array($caste, $castes)) {
                $errors[] = $this->setError($rowNo, trans('contact.caste.caste'), 'invalid');
            }

            if ($religion && ! in_array($religion, $religions)) {
                $errors[] = $this->setError($rowNo, trans('contact.religion.religion'), 'invalid');
            }

            $uniqueId1 = Arr::get($row, 'unique_id1');
            $uniqueId2 = Arr::get($row, 'unique_id2');
            $uniqueId3 = Arr::get($row, 'unique_id3');
            $uniqueId4 = Arr::get($row, 'unique_id4');
            $uniqueId5 = Arr::get($row, 'unique_id5');

            if ($uniqueId1 && in_array($uniqueId1, $newUniqueIdNumber1s)) {
                $errors[] = $this->setError($rowNo, config('config.employee.unique_id_number1_label'), 'duplicate');
            }

            if ($uniqueId2 && in_array($uniqueId2, $newUniqueIdNumber2s)) {
                $errors[] = $this->setError($rowNo, config('config.employee.unique_id_number2_label'), 'duplicate');
            }

            if ($uniqueId3 && in_array($uniqueId3, $newUniqueIdNumber3s)) {
                $errors[] = $this->setError($rowNo, config('config.employee.unique_id_number3_label'), 'duplicate');
            }

            if ($uniqueId4 && in_array($uniqueId4, $newUniqueIdNumber4s)) {
                $errors[] = $this->setError($rowNo, config('config.employee.unique_id_number4_label'), 'duplicate');
            }

            if ($uniqueId5 && in_array($uniqueId5, $newUniqueIdNumber5s)) {
                $errors[] = $this->setError($rowNo, config('config.employee.unique_id_number5_label'), 'duplicate');
            }

            if ($uniqueId1) {
                $newUniqueIdNumber1s[] = $uniqueId1;
            }

            if ($uniqueId2) {
                $newUniqueIdNumber2s[] = $uniqueId2;
            }

            if ($uniqueId3) {
                $newUniqueIdNumber3s[] = $uniqueId3;
            }

            if ($uniqueId4) {
                $newUniqueIdNumber4s[] = $uniqueId4;
            }

            if ($uniqueId5) {
                $newUniqueIdNumber5s[] = $uniqueId5;
            }

            $student = $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first();

            $row['student_id'] = $student?->id;
            $row['contact_id'] = $student?->contact_id;
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
