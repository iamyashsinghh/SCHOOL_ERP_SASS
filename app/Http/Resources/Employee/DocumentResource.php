<?php

namespace App\Http\Resources\Employee;

use App\Enums\VerificationStatus;
use App\Http\Resources\Employee\Config\DocumentTypeResource;
use App\Http\Resources\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $selfUpload = (bool) $this->getMeta('self_upload');

        return [
            'uuid' => $this->uuid,
            'employee' => EmployeeSummaryResource::make($this->whenLoaded('documentable')),
            'type' => DocumentTypeResource::make($this->whenLoaded('type')),
            'title' => $this->title,
            'number' => $this->number,
            'description' => $this->description,
            'issue_date' => $this->issue_date,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_expired' => $this->is_expired,
            'is_verified' => $this->is_verified,
            'self_upload' => $selfUpload,
            $this->mergeWhen($selfUpload, [
                'verification_status' => VerificationStatus::getDetail($this->verification_status),
                'verified_at' => $this->verified_at,
                'verified_by' => $this->getMeta('verified_by'),
                'comment' => $this->getMeta('comment'),
            ]),
            'is_submitted_original' => (bool) $this->getMeta('is_submitted_original'),
            'media_token' => $this->getMeta('media_token', (string) Str::uuid()),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
