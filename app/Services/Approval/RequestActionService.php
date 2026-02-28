<?php

namespace App\Services\Approval;

use App\Actions\Student\TransferStudent;
use App\Enums\Approval\Category;
use App\Enums\Approval\Event;
use App\Enums\Approval\Status;
use App\Models\Tenant\Approval\Request as ApprovalRequest;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Media;
use App\Models\Tenant\RequestRecord;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class RequestActionService
{
    public function preRequisite(Request $request, ApprovalRequest $approvalRequest): array
    {
        if (! $approvalRequest->isActionable()) {
            return [];
        }

        $allowedActions = $approvalRequest->getAllowedActions();
        $statuses = $approvalRequest->getAllowedStatuses($allowedActions);

        return compact('statuses', 'allowedActions');
    }

    public function cancel(Request $request, ApprovalRequest $approvalRequest): void
    {
        if (! $approvalRequest->is_editable) {
            throw ValidationException::withMessages([
                'status' => trans('user.errors.permission_denied'),
            ]);
        }

        \DB::beginTransaction();

        $approvalRequest->status = Status::CANCELLED->value;
        $approvalRequest->save();

        foreach ($approvalRequest->requestRecords as $requestRecord) {
            $requestRecord->status = Status::CANCELLED->value;
            $requestRecord->save();
        }

        $approvalRequest->record(activity: 'status_updated', event: 'updated', attributes: [
            'title' => $approvalRequest->title,
            'status' => Status::CANCELLED->value,
            'code_number' => $approvalRequest->code_number,
        ]);

        \DB::commit();
    }

    public function updateStatus(Request $request, ApprovalRequest $approvalRequest): void
    {
        $request->validate([
            'status' => ['required', new Enum(Status::class)],
            'comment' => ['required_unless:status,approved', 'min:2', 'max:255'],
        ], [], [
            'status' => trans('approval.request.props.status'),
            'comment' => trans('approval.request.props.comment'),
        ]);

        if (! $approvalRequest->isActionable()) {
            throw ValidationException::withMessages([
                'status' => trans('user.errors.permission_denied'),
            ]);
        }

        $allowedActions = $approvalRequest->getAllowedActions();
        $allowedStatuses = $approvalRequest->getAllowedStatuses($allowedActions);

        if (! in_array($request->status, collect($allowedStatuses)->pluck('value')->toArray())) {
            throw ValidationException::withMessages([
                'status' => trans('general.errors.invalid_input'),
            ]);
        }

        $requestRecords = RequestRecord::query()
            ->where('model_type', 'ApprovalRequest')
            ->where('model_id', $approvalRequest->id)
            ->get();

        $requestRecord = $requestRecords->where('user_id', auth()->id())->first();
        $nextRequestRecord = $requestRecords->where('id', '>', $requestRecord->id)->first();

        if (! $requestRecord) {
            throw ValidationException::withMessages([
                'status' => trans('general.errors.invalid_action'),
            ]);
        }

        if ($request->status == Status::RETURNED->value) {
            $this->updateReturnTo($request, $approvalRequest, $requestRecords);
        }

        if ($request->status == Status::HOLD->value) {
            $requestRecord->status = $request->status;
            $requestRecord->comment = $request->comment;
            $requestRecord->save();

            $approvalRequest->status = $request->status;
            $approvalRequest->save();

            return;
        }

        if ($request->status == Status::APPROVED->value && $nextRequestRecord) {
            $approvalRequest->status = Status::REQUESTED->value;
            $approvalRequest->save();
        }

        \DB::beginTransaction();

        if ($approvalRequest->status == Status::HOLD->value && $request->status == Status::APPROVED->value) {
            $approvalRequest->status = $request->status;
            $approvalRequest->save();
        }

        $requestRecord->status = $request->status;
        $requestRecord->comment = $request->comment;
        $requestRecord->processed_at = now()->toDateTimeString();
        $requestRecord->save();

        $nextRecordRequest = $requestRecords->where('id', '>', $requestRecord->id)->first();

        if ($nextRecordRequest && in_array($request->status, [Status::APPROVED->value])) {
            $nextRecordRequest->received_at = now()->toDateTimeString();
            $nextRecordRequest->processed_at = null;
            $nextRecordRequest->status = Status::REQUESTED->value;
            $nextRecordRequest->save();
        }

        $lastRequestRecord = $requestRecords->last();

        if ($lastRequestRecord->id == $requestRecord->id || ! in_array($request->status, [Status::APPROVED->value])) {
            $approvalRequest->status = $request->status;
            $approvalRequest->save();
        }

        $activity = 'status_updated';
        if ($request->status == 'returned') {
            $activity = 'returned';
        }

        if ($approvalRequest->status == Status::APPROVED->value) {
            $this->updateEventBasedRequest($approvalRequest);
        }

        $approvalRequest->record(activity: $activity, event: 'updated', attributes: [
            'title' => $approvalRequest->title,
            'status' => $request->status,
            'code_number' => $approvalRequest->code_number,
            'return_to_employee' => $request->return_to_employee,
        ]);

        \DB::commit();
    }

    private function updateEventBasedRequest(ApprovalRequest $approvalRequest): void
    {
        if ($approvalRequest->type->category != Category::EVENT_BASED) {
            return;
        }

        if ($approvalRequest->type->event == Event::STUDENT_TRANSFER) {
            $this->updateStudentTransferRequest($approvalRequest);
        }
    }

    private function updateStudentTransferRequest(ApprovalRequest $approvalRequest): void
    {
        $student = Student::query()
            ->where('students.id', $approvalRequest->model_id)
            ->first();

        if (! $student) {
            return;
        }

        (new TransferStudent)->execute($student, [
            'date' => $approvalRequest->date->value,
            'transfer_certificate_number' => $approvalRequest->getMeta('transfer_certificate_number'),
            'transfer_request' => (bool) $approvalRequest->getMeta('transfer_request'),
            'reason_id' => $approvalRequest->getMeta('reason_id'),
            'remarks' => $approvalRequest->getMeta('remarks'),
        ]);
    }

    private function updateReturnTo(Request $request, ApprovalRequest $approvalRequest, Collection $requestRecords): void
    {
        $returnToEmployeeUuid = $request->return_to;

        $employees = Employee::query()
            ->select('employees.uuid', 'contacts.id', 'contacts.user_id', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'))
            ->join('contacts', 'employees.contact_id', '=', 'contacts.id')
            ->whereIn('contacts.user_id', array_merge([$approvalRequest->request_user_id], $requestRecords->pluck('user_id')->toArray()))
            ->get();

        $returnToEmployee = $returnToEmployeeUuid == 'requester' ? $employees->firstWhere('user_id', $approvalRequest->request_user_id)?->name : $employees->firstWhere('uuid', $returnToEmployeeUuid)?->name;

        $request->merge([
            'return_to_employee' => $returnToEmployee,
        ]);

        if ($returnToEmployeeUuid == 'requester') {
            $approvalRequest->setMeta([
                'return_to_requester' => true,
            ]);
            $approvalRequest->save();

            foreach ($requestRecords as $requestRecord) {
                $requestRecord->received_at = null;
                $requestRecord->processed_at = null;
                $requestRecord->status = Status::REQUESTED->value;
                $requestRecord->save();
            }

            return;
        }

        $returnToRequestRecord = $requestRecords->filter(function ($requestRecord) use ($employees, $returnToEmployeeUuid) {
            $employee = $employees->firstWhere('uuid', $returnToEmployeeUuid);

            return $requestRecord->user_id == $employee?->user_id;
        })->first();

        foreach ($requestRecords as $requestRecord) {
            if ($returnToRequestRecord?->id && $requestRecord->id >= $returnToRequestRecord->id) {
                $requestRecord->status = $requestRecord->user_id == auth()->id() ? Status::RETURNED->value : Status::REQUESTED->value;
                $requestRecord->received_at = $requestRecord->id == $returnToRequestRecord->id ? now()->toDateTimeString() : null;
                $requestRecord->processed_at = null;
                $requestRecord->save();
            }
        }
    }

    private function isEditable(ApprovalRequest $approvalRequest): bool
    {
        if (! $approvalRequest->is_editable) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        return true;
    }

    public function uploadMedia(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->isEditable($approvalRequest);

        $approvalRequest->updateMedia($request);

        $requestRecords = $approvalRequest->requestRecords;
        $requestRecord = $approvalRequest->request_user_id != auth()->id() ? $requestRecords->where('user_id', auth()->id())->first() : null;

        if ($approvalRequest->status == Status::RETURNED->value) {
            if ($approvalRequest->request_user_id == auth()->id()) {
                $approvalRequest->status = Status::REQUESTED->value;
                $approvalRequest->setMeta([
                    'return_to_requester' => false,
                ]);
                $approvalRequest->save();

                $firstRequestRecord = $requestRecords->first();
                $firstRequestRecord->status = Status::REQUESTED->value;
                $firstRequestRecord->received_at = now()->toDateTimeString();
                $firstRequestRecord->save();
            } elseif ($requestRecord->status == Status::REQUESTED->value && ! empty($requestRecord->received_at->value) && empty($requestRecord->processed_at->value)) {
                // nothing to do
            }
        }

        $approvalRequest->record(activity: 'media_uploaded', event: 'updated', attributes: [
            'title' => $approvalRequest->title,
            'code_number' => $approvalRequest->code_number,
        ]);
    }

    public function removeMedia(ApprovalRequest $approvalRequest, string $uuid)
    {
        $this->isEditable($approvalRequest);

        $media = Media::query()
            ->whereModelType($approvalRequest->getModelName())
            ->where('status', 1)
            ->whereModelId($approvalRequest->id)
            ->whereUuid($uuid)
            ->getOrFail(trans('general.file'));

        if (\Storage::exists($media->name)) {
            \Storage::delete($media->name);
        }

        $media->delete();

        $approvalRequest->record(activity: 'media_removed', event: 'updated', attributes: [
            'title' => $approvalRequest->title,
            'filename' => $media->file_name,
            'code_number' => $approvalRequest->code_number,
        ]);
    }
}
