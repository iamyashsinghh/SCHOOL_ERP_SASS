<?php

namespace App\Services\Dashboard;

use App\Enums\Employee\Type as EmployeeType;
use App\Models\Employee\Attendance\Attendance;
use App\Models\Employee\Attendance\Type as AttendanceType;
use App\Models\Employee\Employee;

class EmployeeAttendanceSummaryService
{
    public function fetch($request)
    {
        $employees = Employee::query()
            ->select('employees.id', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'), 'code_number')
            ->join('contacts', 'contacts.id', '=', 'employees.contact_id')
            ->whereIn('type', [EmployeeType::ADMINISTRATIVE, EmployeeType::TEACHING])
            ->where(function ($q) {
                $q->whereNull('leaving_date')
                    ->orWhere(function ($q) {
                        $q->whereNotNull('leaving_date')
                            ->where('leaving_date', '>=', today()->toDateString());
                    });
            })
            ->get();

        $total = $employees->count();

        $attendanceTypes = AttendanceType::query()
            ->byTeam()
            ->direct()
            ->get();

        $attendances = Attendance::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('date', today()->toDateString())
            ->get();

        $data = [];
        foreach ($attendanceTypes as $type) {
            $filteredAttendances = $attendances->where('attendance_type_id', $type->id);
            $count = $filteredAttendances->count();
            $percent = round(($total > 0 ? ($count / $total) * 100 : 0), 2);

            if ($count === 0) {
                continue;
            }

            $filteredEmployees = $employees->whereIn('id', $filteredAttendances->pluck('employee_id'));

            $data[] = [
                'code' => $type->code,
                'label' => $type->name,
                'value' => $count,
                'percent' => $percent,
                'percentage' => \Percent::from($percent)->formatted,
                'color' => \Percent::from($percent)->getPercentageColor(),
                'max' => $total,
                'employees' => $filteredEmployees->map(function ($employee) {
                    return [
                        'name' => $employee->name,
                        'code_number' => $employee->code_number,
                    ];
                })->values(),
            ];
        }

        return [
            'employeeAttendanceSummary' => $data,
        ];
    }
}