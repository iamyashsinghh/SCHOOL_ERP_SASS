<?php

namespace App\Actions\Employee\Payroll;

use App\Enums\Employee\Payroll\PayrollStatus;
use App\Helpers\CalHelper;
use App\Models\Tenant\Employee\Attendance\Type as AttendanceType;
use App\Models\Tenant\Employee\Leave\Request as LeaveRequest;
use App\Models\Tenant\Employee\Payroll\Payroll;
use App\Models\Tenant\Employee\Payroll\SalaryStructure;
use Illuminate\Support\Arr;

class ValidatePayrollInput
{
    public function execute(array $params = []): array
    {
        $uuid = Arr::get($params, 'uuid');
        $employeeId = Arr::get($params, 'employee_id');
        $startDate = Arr::get($params, 'start_date');
        $endDate = Arr::get($params, 'end_date');
        $teamId = Arr::get($params, 'team_id');

        if (empty($teamId) && auth()->check()) {
            $teamId = auth()->user()->current_team_id;
        }

        $processedPayroll = Payroll::query()
            ->when($uuid, function ($q) use ($uuid) {
                $q->where('uuid', '!=', $uuid);
            })
            ->whereEmployeeId($employeeId)
            ->where('start_date', '>', $startDate)
            ->where('status', PayrollStatus::PROCESSED->value)
            ->exists();

        if ($processedPayroll) {
            return [
                'status' => false,
                'code' => 'payroll_generated',
                'message' => trans('employee.payroll.could_not_perform_if_payroll_generated_for_later_date'),
            ];
        }

        $overlappingPayroll = Payroll::query()
            ->when($uuid, function ($q) use ($uuid) {
                $q->where('uuid', '!=', $uuid);
            })
            ->whereEmployeeId($employeeId)
            ->betweenPeriod($startDate, $endDate)
            ->where('status', PayrollStatus::PROCESSED->value)
            ->exists();

        if ($overlappingPayroll) {
            return [
                'status' => false,
                'code' => 'overlapping_payroll',
                'message' => trans('employee.payroll.range_exists', ['start' => CalHelper::showDate($startDate), 'end' => CalHelper::showDate($endDate)]),
            ];
        }

        $pendingLeaveRequests = LeaveRequest::query()
            ->whereModelType('Employee')
            ->whereModelId($employeeId)
            ->betweenPeriod($startDate, $endDate)
            ->whereIn('status', [
                'requested',
            ])
            ->exists();

        if ($pendingLeaveRequests) {
            return [
                'status' => false,
                'code' => 'pending_leave_request',
                'message' => trans('employee.payroll.pending_leave_request_exists_in_given_date_range'),
            ];
        }

        $salaryStructure = SalaryStructure::query()
            ->whereEmployeeId($employeeId)
            ->where('effective_date', '<=', $startDate)
            ->orderBy('effective_date', 'desc')
            ->first();

        if (! $salaryStructure) {
            return [
                'status' => false,
                'code' => 'salary_structure_not_found',
                'message' => trans('global.could_not_find', ['attribute' => trans('employee.payroll.salary_structure.salary_structure')]),
            ];
        }

        $attendanceTypes = AttendanceType::query()
            ->byTeam($teamId)
            ->direct()
            ->get();

        $productionAttendanceTypes = AttendanceType::query()
            ->byTeam($teamId)
            ->productionBased()
            ->get();

        return [
            'status' => true,
            'salary_structure' => $salaryStructure,
            'attendance_types' => $attendanceTypes,
            'has_hourly_payroll' => (bool) $salaryStructure->template->has_hourly_payroll,
            'production_attendance_types' => $productionAttendanceTypes,
        ];
    }
}
