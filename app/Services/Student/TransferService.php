<?php

namespace App\Services\Student;

use App\Actions\Approval\CreateRequest as CreateApprovalRequest;
use App\Actions\Student\CancelTransferStudent;
use App\Actions\Student\TransferStudent;
use App\Enums\Approval\Category;
use App\Enums\Approval\Event;
use App\Enums\Approval\Status as ApprovalRequestStatus;
use App\Enums\OptionType;
use App\Http\Resources\OptionResource;
use App\Http\Resources\TeamSummaryResource;
use App\Models\Tenant\Approval\Request as ApprovalRequest;
use App\Models\Tenant\Approval\Type;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Admission;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function preRequisite(Request $request): array
    {
        $reasons = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_TRANSFER_REASON->value)
            ->get());

        $approvalType = Type::query()
            ->where('category', Category::EVENT_BASED->value)
            ->where('event', Event::STUDENT_TRANSFER->value)
            ->exists() ? true : false;

        $teams = Team::query()
            ->where('id', '!=', auth()->user()->current_team_id)
            ->get();

        $actions = [
            ['label' => trans('student.transfer.actions.transfer_other_team'), 'value' => 'transfer_other_team'],
        ];

        if (count($teams)) {
            $actions[] = ['label' => trans('student.transfer.actions.transfer_within_team'), 'value' => 'transfer_within_team'];
        }

        $teams = TeamSummaryResource::collection($teams);

        return compact('reasons', 'approvalType', 'teams', 'actions');
    }

    public function create(Request $request): array
    {
        if ($request->action == 'transfer_within_team') {
            throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);
        }

        $result = $this->createApprovalRequest($request, $request->student);

        if ($result) {
            return [
                'action' => 'request',
            ];
        }

        \DB::beginTransaction();

        $student = $request->student;
        (new TransferStudent)->execute($student, [
            'date' => $request->date,
            'transfer_certificate_number' => $request->transfer_certificate_number,
            'transfer_request' => false,
            'reason_id' => $request->reason_id,
            'remarks' => $request->remarks,
        ]);

        \DB::commit();

        return [
            'action' => 'transfer',
        ];
    }

    private function createApprovalRequest(Request $request, Student $student): bool
    {
        $approvalType = Type::query()
            ->where('category', Category::EVENT_BASED->value)
            ->where('event', Event::STUDENT_TRANSFER->value)
            ->first();

        if (! $approvalType) {
            return false;
        }

        $existingRequest = ApprovalRequest::query()
            ->where('type_id', $approvalType->id)
            ->where('model_type', 'Student')
            ->where('model_id', $request->student->id)
            ->whereNotIn('status', [
                ApprovalRequestStatus::APPROVED->value,
                ApprovalRequestStatus::REJECTED->value,
                ApprovalRequestStatus::CANCELLED->value,
            ])
            ->first();

        if ($existingRequest) {
            throw ValidationException::withMessages(['message' => trans('student.transfer.request_already_submitted', ['attribute' => $existingRequest->code_number])]);
        }

        $approvalRequest = (new CreateApprovalRequest)->execute($request, $approvalType, [
            'title' => trans('student.transfer.transfer'),
            'model_type' => 'Student',
            'model_id' => $request->student->id,
            'meta' => [
                'transfer_certificate_number' => $request->transfer_certificate_number,
                'reason_id' => $request->reason_id,
                'remarks' => $request->remarks,
            ],
        ]);

        $admission = $student->admission;
        $admission->setMeta([
            'transfer_approval_request_id' => $approvalRequest->id,
        ]);
        $admission->save();

        return true;
    }

    public function update(Request $request, Student $student): void
    {
        if ($student->getMeta('transfer_request')) {
            throw ValidationException::withMessages(['message' => trans('student.transfer.could_not_perform_if_transfer_request')]);
        }

        $admission = $student->admission;

        if ($admission->getMeta('transfer_approval_request_id')) {
            throw ValidationException::withMessages(['message' => trans('student.transfer.could_not_perform_if_transfer_approval_request')]);
        }

        if ($student->uuid != $request->student->uuid) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        \DB::beginTransaction();

        $student->end_date = $request->date;
        $student->setMeta([
            'transfer_certificate_number' => $request->transfer_certificate_number,
        ]);
        $student->save();

        $admission = Admission::query()
            ->whereId($student->admission_id)
            ->first();

        $admission->leaving_date = $request->date;
        $admission->transfer_reason_id = $request->reason_id;
        $admission->leaving_remarks = $request->remarks;
        $admission->save();

        \DB::commit();
    }

    public function deletable(Student $student): void
    {
        //
    }

    public function delete(Student $student): void
    {
        if ($student->getMeta('transfer_request')) {
            throw ValidationException::withMessages(['message' => trans('student.transfer.could_not_perform_if_transfer_request')]);
        }

        $admission = $student->admission;

        if ($admission->getMeta('transfer_approval_request_id')) {
            throw ValidationException::withMessages(['message' => trans('student.transfer.could_not_perform_if_transfer_approval_request')]);
        }

        \DB::beginTransaction();

        (new CancelTransferStudent)->execute($student);

        \DB::commit();
    }
}
