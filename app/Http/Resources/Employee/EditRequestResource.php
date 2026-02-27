<?php

namespace App\Http\Resources\Employee;

use App\Enums\ContactEditStatus;
use App\Enums\FamilyRelation;
use App\Enums\Gender;
use App\Http\Resources\MediaResource;
use App\Http\Resources\UserSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class EditRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'employee' => EmployeeSummaryResource::make($this->whenLoaded('model')),
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'status' => ContactEditStatus::getDetail($this->status),
            'is_rejected' => $this->status == ContactEditStatus::REJECTED ? true : false,
            'comment' => $this->comment,
            'data' => $this->formatData($this->data),
            'processed_at' => \Cal::dateTime($this->processed_at),
            'media_token' => $this->getMeta('media_token'),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function formatData($data)
    {
        $new = Arr::get($data, 'new', []);
        $old = Arr::get($data, 'old', []);

        if (array_key_exists('birth_date', $new)) {
            $new['birth_date'] = \Cal::date($new['birth_date']);
            $old['birth_date'] = \Cal::date($old['birth_date']);
        }

        if (array_key_exists('gender', $new)) {
            $new['gender'] = Gender::getDetail($new['gender']);
            $old['gender'] = Gender::getDetail($old['gender']);
        }

        if (Arr::has($new, 'emergency_contact.relation')) {
            $new['emergency_contact']['relation'] = FamilyRelation::getDetail($new['emergency_contact']['relation']);
            $old['emergency_contact']['relation'] = FamilyRelation::getDetail($old['emergency_contact']['relation']);
        }

        return [
            'new' => $new,
            'old' => $old,
        ];
    }
}
