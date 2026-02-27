<?php

namespace App\Http\Resources\Employee;

use App\Enums\BloodGroup;
use App\Enums\Employee\Status;
use App\Enums\Employee\Type;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EmployeeListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $status = Status::ACTIVE;

        if ($this->leaving_date->value && $this->leaving_date->value < today()->toDateString()) {
            $status = Status::INACTIVE;
        }

        $address = json_decode($this->address, true);

        $birthDate = \Cal::date($this->birth_date);
        $startDate = \Cal::date($this->start_date);
        $endDate = \Cal::date($this->end_date);

        $documentTypes = $request->document_types ?? collect([]);

        return [
            'uuid' => $this->uuid,
            'code_number' => $this->code_number,
            'type' => Type::getDetail($this->type),
            'team_name' => $this->team_name ?? '-',
            'other_team_name' => $this->other_team_name ?? '-',
            'other_team_member' => (bool) $this->getMeta('other_team_member'),
            'name' => $this->name,
            'contact_number' => $this->contact_number,
            'employment_status' => $this->employment_status_name ?? '-',
            'department' => $this->department_name ?? '-',
            'designation' => $this->designation_name ?? '-',
            'employment_status_uuid' => $this->employment_status_uuid,
            'department_uuid' => $this->department_uuid,
            'designation_uuid' => $this->designation_uuid,
            'is_default' => $this->is_default,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'gender' => Gender::getDetail($this->gender),
            'blood_group' => BloodGroup::getDetail($this->blood_group),
            'marital_status' => MaritalStatus::getDetail($this->marital_status),
            'religion_name' => $this->religion_name,
            'caste_name' => $this->caste_name,
            'category_name' => $this->category_name,
            'photo' => $this->photo_url,
            'self' => $this->user_id == auth()->id() ? true : false,
            'birth_date' => $birthDate,
            'age' => $birthDate->age,
            'joining_date' => $this->joining_date,
            'leaving_date' => $this->leaving_date,
            'period' => $this->period,
            'status' => Status::getDetail($status),
            'start_date' => $startDate,
            'end_date' => $endDate,
            ...$this->getDocumentNumbers($documentTypes),
            'address' => Arr::toAddress([
                'address_line1' => Arr::get($address, 'present.address_line1'),
                'address_line2' => Arr::get($address, 'present.address_line2'),
                'city' => Arr::get($address, 'present.city'),
                'state' => Arr::get($address, 'present.state'),
                'zipcode' => Arr::get($address, 'present.zipcode'),
                'country' => Arr::get($address, 'present.country'),
            ]),
            'user_uuid' => $this->user_uuid,
            'created_at' => \Cal::dateTime($this->created_at),
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
}
