<?php

namespace App\Http\Resources\Student;

use App\Enums\ContactEditStatus;
use App\Enums\FamilyRelation;
use App\Enums\Gender;
use App\Http\Resources\ContactSummaryResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\UserSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ProfileEditRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = [
            'new' => collect($this->data['new'] ?? [])->map(function ($item, $key) {
                if (str_contains($key, '_date')) {
                    $item = \Cal::date($item);
                }

                return $item;
            })->toArray(),
            'old' => collect($this->data['old'] ?? [])->map(function ($item, $key) {
                if (str_contains($key, '_date')) {
                    $item = \Cal::date($item);
                }

                return $item;
            })->toArray(),
        ];

        return [
            'uuid' => $this->uuid,
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'contact' => ContactSummaryResource::make($this->model->contact),
            'status' => ContactEditStatus::getDetail($this->status),
            'is_rejected' => $this->status == ContactEditStatus::REJECTED ? true : false,
            'is_approved' => $this->status == ContactEditStatus::APPROVED ? true : false,
            'comment' => $this->comment,
            'processed_at' => \Cal::dateTime($this->processed_at),
            'data' => $this->formatData($data),
            'processed_by' => $this->getMeta('processed_by'),
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

        // already formatted in collection
        // if (array_key_exists('birth_date', $new)) {
        //     $new['birth_date'] = \Cal::date($new['birth_date']);
        //     $old['birth_date'] = \Cal::date($old['birth_date']);
        // }

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
