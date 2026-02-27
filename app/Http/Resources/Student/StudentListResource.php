<?php

namespace App\Http\Resources\Student;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Locality;
use App\Enums\Student\StudentType;
use App\Http\Resources\Employee\EmployeeSummaryResource;
use App\Http\Resources\GuardianResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StudentListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $address = json_decode($this->address, true);

        $documentTypes = $request->document_types ?? collect([]);

        return [
            'uuid' => $this->uuid,
            'record_uuid' => $this->record_uuid,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'code_number' => $this->code_number,
            'roll_number' => $this->roll_number,
            'is_provisional' => (bool) $this->is_provisional,
            'joining_date' => \Cal::date($this->joining_date),
            'leaving_date' => \Cal::date($this->leaving_date),
            'cancelled_at' => \Cal::date($this->cancelled_at),
            'start_date' => \Cal::date($this->start_date),
            'end_date' => \Cal::date($this->end_date),
            'batch_name' => $this->batch_name,
            'course_name' => $this->course_name,
            'course_term' => $this->course_term,
            'gender' => Gender::getDetail($this->gender),
            'birth_date' => \Cal::date($this->birth_date),
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'photo' => $this->photo_url,
            'unique_id_number1' => $this->unique_id_number1,
            'unique_id_number2' => $this->unique_id_number2,
            'unique_id_number3' => $this->unique_id_number3,
            'unique_id_number4' => $this->unique_id_number4,
            'unique_id_number5' => $this->unique_id_number5,
            ...$this->getDocumentNumbers($documentTypes),
            'guardian' => GuardianResource::make($this->guardian),
            $this->mergeWhen($request->query('details'), [
                'mentor' => EmployeeSummaryResource::make($this->whenLoaded('mentor')),
                'groups' => $this->getGroupNames(),
            ]),
            'is_transferred' => $this->leaving_date ? true : false,
            'religion_name' => $this->religion_name,
            'caste_name' => $this->caste_name,
            'category_name' => $this->category_name,
            'address' => Arr::toAddress([
                'address_line1' => Arr::get($address, 'present.address_line1'),
                'address_line2' => Arr::get($address, 'present.address_line2'),
                'city' => Arr::get($address, 'present.city'),
                'state' => Arr::get($address, 'present.state'),
                'zipcode' => Arr::get($address, 'present.zipcode'),
                'country' => Arr::get($address, 'present.country'),
            ]),
            'user_uuid' => $this->user_uuid,
            'enrollment_type_name' => $this->enrollment_type_name,
            'enrollment_status_name' => $this->enrollment_status_name,
            'locality' => Locality::getDetail($this->locality),
            'blood_group' => BloodGroup::getDetail($this->blood_group),
            'is_alumni' => (bool) $this->getMeta('is_alumni'),
            $this->mergeWhen($this->getMeta('is_alumni'), [
                'alumni_date' => \Cal::date($this->getMeta('alumni_date')),
            ]),
            'student_type' => StudentType::getDetail($this->getMeta('student_type', 'old')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function getDocumentNumbers($documentTypes)
    {
        $documentNumbers = [];
        foreach ($documentTypes as $documentType) {
            $documentNumbers[Str::camel(strtolower($documentType->name))] = $this->contact->documents->where('type_id', $documentType->id)->first()?->number;
        }

        return $documentNumbers;
    }

    private function getGroupNames()
    {
        if (! $this->relationLoaded('groups')) {
            return null;
        }

        return $this->groups->map(function ($group) {
            return $group->modelGroup?->name;
        })->implode(', ');
    }
}
