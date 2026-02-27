<?php

namespace App\Http\Resources\Student;

use App\Enums\Student\StudentType;
use App\Http\Resources\Academic\BatchResource;
use App\Http\Resources\Academic\PeriodResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\Employee\EmployeeSummaryResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\TagResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $isTransferred = $this->leaving_date ? true : false;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'number' => $this->number,
            'number_format' => $this->number_format,
            'roll_number' => $this->roll_number,
            'code_number' => $this->code_number,
            'is_provisional' => (bool) $this->is_provisional,
            'joining_date' => \Cal::date($this->joining_date),
            'leaving_date' => \Cal::date($this->leaving_date),
            'batch_uuid' => $this->batch_uuid,
            'course_uuid' => $this->course_uuid,
            'batch_name' => $this->batch_name,
            'course_name' => $this->course_name,
            'course_term' => $this->course_term,
            'enrollment_type_name' => $this->enrollment_type_name,
            'enrollment_status_name' => $this->enrollment_status_name,
            'mentor' => EmployeeSummaryResource::make($this->whenLoaded('mentor')),
            'enrollment_type' => OptionResource::make($this->whenLoaded('enrollmentType')),
            'enrollment_status' => OptionResource::make($this->whenLoaded('enrollmentStatus')),
            'enrollment_status_logs' => collect($this->getMeta('enrollment_status_logs', []))->map(function ($log) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'enrollment_status' => Arr::get($log, 'enrollment_status'),
                    'created_at' => \Cal::dateTime(Arr::get($log, 'created_at')),
                    'created_by' => Arr::get($log, 'created_by'),
                ];
            }),
            $this->mergeWhen($request->with_guardian_photo, [
                'father_guardian_uuid' => $this->father_guardian_uuid,
                'mother_guardian_uuid' => $this->mother_guardian_uuid,
                'father_photo' => $this->father_photo,
                'mother_photo' => $this->mother_photo,
            ]),
            'admission' => AdmissionResource::make($this->whenLoaded('admission')),
            'contact' => ContactResource::make($this->whenLoaded('contact')),
            'period' => PeriodResource::make($this->whenLoaded('period')),
            'batch' => BatchResource::make($this->whenLoaded('batch')),
            'student_type' => StudentType::getDetail($this->getMeta('student_type', 'old')),
            'has_fee_structure_set' => $this->fee_structure_id ? true : false,
            $this->mergeWhen($request->include_elective_subject, [
                'has_elective_subject' => $this->has_elective_subject ? true : false,
            ]),
            $this->mergeWhen($this->has_exam_mark, [
                'is_not_applicable' => $this->is_not_applicable,
                'marks' => $this->marks,
                'comment' => $this->comment,
                'incharge_comment' => $this->incharge_comment,
                'result' => $this->result,
                'attendance' => $this->attendance,
            ]),
            $this->mergeWhen($request->has_timesheet, [
                'in_at' => $this->in_at,
                'out_at' => $this->out_at,
                'status' => $this->status,
            ]),
            $this->mergeWhen($request->has_records, [
                'records' => $this->records,
            ]),
            'is_admitted' => $this->joining_date == $this->start_date->value ? true : false,
            'is_promoted' => $this->joining_date != $this->start_date->value ? true : false,
            $this->mergeWhen($this->has_health_record, [
                'height' => $this->height,
                'weight' => $this->weight,
                'chest' => $this->chest,
                'left_eye' => $this->left_eye,
                'right_eye' => $this->right_eye,
                'dental_hygiene' => $this->dental_hygiene,
            ]),
            'is_transferred' => $isTransferred,
            $this->mergeWhen($isTransferred, [
                'transfer_reason' => $this->transfer_reason,
                'transfer_certificate_number' => $this->getMeta('transfer_certificate_number'),
            ]),
            $this->mergeWhen($request->show_batch_change_history, [
                'batch_change_history' => collect($this->getMeta('batch_history', []))->map(function ($history) {
                    return [
                        'old_batch' => Arr::get($history, 'old_batch'),
                        'new_batch' => Arr::get($history, 'new_batch'),
                        'start_date' => \Cal::date(Arr::get($history, 'start_date')),
                        'end_date' => \Cal::date(Arr::get($history, 'end_date')),
                        'changed_at' => \Cal::dateTime(Arr::get($history, 'changed_at')),
                        'changed_by' => Arr::get($history, 'changed_by'),
                    ];
                }),
            ]),
            'photo' => $this->photo_url,
            'photo_url' => url($this->photo_url),
            'start_date' => \Cal::date($this->start_date),
            'end_date' => \Cal::date($this->end_date),
            'cancelled_at' => \Cal::dateTime($this->cancelled_at),
            'is_alumni' => (bool) $this->getMeta('is_alumni'),
            $this->mergeWhen($this->getMeta('is_alumni'), [
                'alumni_date' => \Cal::date($this->getMeta('alumni_date')),
            ]),
            'promotion_batch' => $this->getMeta('promotion_batch'),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tag_summary' => $this->showTags(),
            $this->mergeWhen($this->whenLoaded('groups'), [
                'groups' => $this->groups->map(function ($group) {
                    return [
                        'uuid' => $group->modelGroup->uuid,
                        'name' => $group->modelGroup->name,
                    ];
                }),
            ]),
            'fee_locked_at' => \Cal::dateTime($this->getMeta('fee_locked_at')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
