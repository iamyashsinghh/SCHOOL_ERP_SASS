<?php

namespace App\Services\Employee\Leave;

use App\Actions\Approval\CreateRequest as CreateApprovalRequest;
use App\Enums\Approval\Category;
use App\Enums\Approval\Event;
use App\Enums\Approval\Status as ApprovalRequestStatus;
use App\Enums\Employee\Leave\RequestStatus as LeaveRequestStatus;
use App\Enums\Employee\Payroll\PayrollStatus;
use App\Http\Resources\Employee\Leave\TypeResource as LeaveTypeResource;
use App\Jobs\Notifications\Employee\Leave\SendLeaveRequestRaisedNotifcation;
use App\Models\Approval\Request as ApprovalRequest;
use App\Models\Approval\Type;
use App\Models\Employee\Employee;
use App\Models\Employee\Leave\Allocation as LeaveAllocation;
use App\Models\Employee\Leave\Request as LeaveRequest;
use App\Models\Employee\Leave\Type as LeaveType;
use App\Models\Employee\Payroll\Payroll;
use App\Models\RequestRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequestService
{
    public function preRequisite(Request $request): array
    {
        $types = LeaveType::query()
            ->byTeam()
            ->get();

        $employee = Employee::query()
            ->auth()
            ->first();

        $leaveAllocation = $employee ? LeaveAllocation::query()
            ->with('records')
            ->whereEmployeeId($employee->id)
            ->where('start_date', '<=', today()->toDateString())
            ->where('end_date', '>=', today()->toDateString())
            ->first() : null;

        $types = $leaveAllocation ? $types->map(function ($type) use ($leaveAllocation) {
            $leaveAllocationRecord = $leaveAllocation->records->where('leave_type_id', $type->id)->first();

            if ($leaveAllocationRecord) {
                $type->balance = $leaveAllocationRecord->allotted - $leaveAllocationRecord->used;
            } else {
                $type->balance = 0;
            }

            return $type;
        }) : $types;

        if ($employee) {
            $request->merge(['has_balance' => true]);
        }

        $types = LeaveTypeResource::collection($types);

        $statuses = LeaveRequestStatus::getOptions();

        return compact('statuses', 'types');
    }

    public function create(Request $request): LeaveRequest
    {
        $result = $this->createApprovalRequest($request);

        \DB::beginTransaction();

        $leaveRequest = LeaveRequest::forceCreate($this->formatParams($request));

        $leaveRequest->addMedia($request);

        \DB::commit();

        SendLeaveRequestRaisedNotifcation::dispatch([
            'leave_request_id' => $leaveRequest->id,
            'team_id' => auth()->user()?->current_team_id,
        ]);

        return $leaveRequest;
    }

    private function createApprovalRequest(Request $request): bool
    {
        $approvalType = Type::query()
            ->where('category', Category::EVENT_BASED->value)
            ->where('event', Event::EMPLOYEE_LEAVE->value)
            ->first();

        if (! $approvalType) {
            return false;
        }

        $existingRequest = ApprovalRequest::query()
            ->where('type_id', $approvalType->id)
            ->where('model_type', 'Employee')
            ->where('model_id', $request->employee_id)
            ->whereNotIn('status', [
                ApprovalRequestStatus::APPROVED->value,
                ApprovalRequestStatus::REJECTED->value,
                ApprovalRequestStatus::CANCELLED->value,
            ])
            ->first();

        if ($existingRequest) {
            throw ValidationException::withMessages(['message' => trans('employee.leave.request.request_already_submitted', ['attribute' => $existingRequest->code_number])]);
        }

        $approvalRequest = (new CreateApprovalRequest)->execute($request, $approvalType, [
            'title' => trans('employee.leave.request.request'),
            'model_type' => 'Employee',
            'model_id' => $request->employee_id,
            'meta' => [],
        ]);

        $request->merge([
            'approval_request_uuid' => $approvalRequest->uuid,
        ]);

        return true;
    }

    private function formatParams(Request $request, ?LeaveRequest $leaveRequest = null): array
    {
        $formatted = [
            'model_type' => 'Employee',
            'model_id' => $request->employee_id,
            'leave_type_id' => $request->leave_type_id,
            'is_half_day' => $request->boolean('is_half_day'),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
        ];

        if (! $leaveRequest) {
            $formatted['status'] = LeaveRequestStatus::REQUESTED;
            $formatted['request_user_id'] = auth()->id();
        }

        $meta = $leaveRequest?->meta ?? [];
        $meta['leave_with_exhausted_credit'] = false;
        $meta['approval_request_uuid'] = $request->approval_request_uuid;
        $meta['has_approval_request'] = $request->approval_request_uuid ? true : false;
        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status != LeaveRequestStatus::REQUESTED) {
            throw ValidationException::withMessages(['message' => trans('employee.leave.request.could_not_perform_if_status_updated')]);
        }

        if ($leaveRequest->getMeta('has_approval_request')) {
            $approvalRequest = ApprovalRequest::query()
                ->where('uuid', $leaveRequest->getMeta('approval_request_uuid'))
                ->firstOrFail();

            $processedRequestRecords = RequestRecord::query()
                ->where('model_type', 'ApprovalRequest')
                ->where('model_id', $approvalRequest->id)
                ->whereNotNull('processed_at')
                ->exists();

            if ($processedRequestRecords) {
                throw ValidationException::withMessages(['message' => trans('employee.leave.request.could_not_perform_if_approval_processed')]);
            }
        }

        \DB::beginTransaction();

        $leaveRequest->forceFill($this->formatParams($request, $leaveRequest))->save();

        $leaveRequest->updateMedia($request);

        \DB::commit();
    }

    public function deletable(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status != LeaveRequestStatus::REQUESTED) {
            throw ValidationException::withMessages(['message' => trans('employee.leave.request.could_not_perform_if_status_updated')]);
        }

        $payrollGenerated = Payroll::query()
            ->whereEmployeeId($leaveRequest->model_id)
            ->betweenPeriod($leaveRequest->start_date->value, $leaveRequest->end_date->value)
            ->where('status', '!=', PayrollStatus::FAILED)
            ->exists();

        if ($payrollGenerated) {
            throw ValidationException::withMessages(['message' => trans('employee.leave.request.could_not_perform_if_payroll_generated')]);
        }
    }

    public function getLeaveAllocation(LeaveRequest $leaveRequest): ?LeaveAllocation
    {
        return LeaveAllocation::query()
            ->with('records.type')
            ->whereEmployeeId($leaveRequest->model_id)
            ->where('start_date', '<=', $leaveRequest->start_date->value)
            ->where('end_date', '>=', $leaveRequest->end_date->value)
            ->first();
    }

    public function getApprovalRequest(LeaveRequest $leaveRequest): ?ApprovalRequest
    {
        if (! $leaveRequest->getMeta('has_approval_request')) {
            return null;
        }

        return ApprovalRequest::query()
            ->where('uuid', $leaveRequest->getMeta('approval_request_uuid'))
            ->firstOrFail();
    }
}
