<?php

namespace App\Http\Resources\Student;

use App\Enums\Approval\Status;
use App\Http\Resources\UserSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferApprovalRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $students = $request->students ?? collect([]);
        $reasons = $request->reasons ?? collect([]);

        $student = $students->firstWhere('id', $this->model_id);
        $reason = $reasons->firstWhere('id', $this->getMeta('reason_id'));

        return [
            'uuid' => $this->uuid,
            'name' => $student->name,
            'code_number' => $this->code_number,
            'student_code_number' => $student->code_number,
            'transfer_certificate_number' => $this->getMeta('transfer_certificate_number'),
            'transfer_request' => (bool) $this->getMeta('transfer_request'),
            'transfer_date' => $this->date,
            'contact_number' => $student->contact_number,
            'father_name' => $student->father_name,
            'mother_name' => $student->mother_name,
            'joining_date' => \Cal::date($student->joining_date),
            'course_name' => $student->course_name,
            'batch_name' => $student->batch_name,
            'reason' => $reason->name,
            'status' => Status::getDetail($this->status),
            'request_user' => UserSummaryResource::make($this->whenLoaded('requestUser')),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
