<?php

namespace App\Http\Resources\Student;

use App\Enums\QualificationResult;
use App\Enums\VerificationStatus;
use App\Helpers\CalHelper;
use App\Http\Resources\MediaResource;
use App\Http\Resources\OptionResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class QualificationsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $students = $request->students ?? collect([]);

        $student = $this->student ? $this->student : $students->firstWhere('contact_id', $this->model_id);

        $selfUpload = (bool) $this->getMeta('self_upload');

        return [
            'uuid' => $this->uuid,
            'student' => StudentSummaryResource::make($student),
            'course' => $this->course,
            'session' => $this->getMeta('session'),
            'institute' => $this->institute,
            'institute_address' => $this->getMeta('institute_address'),
            'affiliated_to' => $this->affiliated_to,
            'level' => OptionResource::make($this->whenLoaded('level')),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'period' => CalHelper::getPeriod($this->start_date->value, $this->end_date->value),
            'result' => QualificationResult::getDetail($this->result),
            'total_marks' => $this->getMeta('total_marks'),
            'obtained_marks' => $this->getMeta('obtained_marks'),
            'percentage' => $this->getMeta('percentage'),
            'failed_subjects' => $this->getMeta('failed_subjects'),
            'is_verified' => $this->is_verified,
            'self_upload' => $selfUpload,
            $this->mergeWhen($selfUpload, [
                'verification_status' => VerificationStatus::getDetail($this->verification_status),
                'verified_at' => $this->verified_at,
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
