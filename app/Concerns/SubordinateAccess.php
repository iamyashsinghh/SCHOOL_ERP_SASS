<?php

namespace App\Concerns;

use App\Models\Tenant\Employee\Designation;
use App\Models\Tenant\Employee\Employee;
use App\Support\HasTree;
use Illuminate\Validation\ValidationException;

trait SubordinateAccess
{
    use HasTree;

    public function getAccessibleDesignationIds(?string $date = null, ?string $permission = null)
    {
        if (auth()->user()->is_default) {
            return;
        }

        if (auth()->user()->hasRole('observer')) {
            return;
        }

        if (! empty($permission) && auth()->user()->can($permission)) {
            return;
        }

        if (empty($permission) && auth()->user()->can('designation:admin-access')) {
            return;
        }

        $date ??= today()->toDateString();

        $employee = Employee::query()
            ->auth()
            ->withCurrentDesignationId()
            ->first();

        $designationIds = [];
        if (! $employee) {
        } elseif (auth()->user()->can('designation:self-access')) {
            array_push($designationIds, $employee->current_designation_id);
        } elseif (auth()->user()->can('designation:subordinate-access')) {
            $designations = Designation::query()
                ->byTeam()
                ->whereNotNull('parent_id')
                ->pluck('parent_id', 'id')
                ->all();

            $childDesignationIds = $this->getChilds($designations, $employee->current_designation_id);

            $designationIds = array_merge($designationIds, $childDesignationIds);
        }

        return $designationIds;
    }

    public function isAccessibleDesignation(Designation $designation)
    {
        $accessibleDesignationIds = Designation::query()
            ->byTeam()
            ->filterAccessible()
            ->pluck('designations.id')
            ->all();

        if (! in_array($designation->id, $accessibleDesignationIds)) {
            return false;
        }

        return true;
    }

    public function getAccessibleEmployeeIds(?string $permission = null)
    {
        return Employee::query()
            ->summary()
            ->when($permission, function ($q) use ($permission) {
                $q->filterAccessibleWithAdditionalPermission($permission);
            }, function ($q) {
                $q->filterAccessible();
            })
            ->pluck('employees.id')
            ->all();
    }

    public function getAccessibleEmployees()
    {
        return Employee::query()
            ->summary()
            ->filterAccessible()
            ->get();
    }

    public function getAccessibleEmployeeContactIds()
    {
        return Employee::query()
            ->summary()
            ->filterAccessible()
            ->pluck('employees.contact_id')
            ->all();
    }

    public function isAccessibleEmployee(Employee $employee, ?string $permission = null)
    {
        $accessibleEmployeeIds = $this->getAccessibleEmployeeIds($permission);

        if (! in_array($employee->id, $accessibleEmployeeIds)) {
            return false;
        }

        return true;
    }

    public function validateEmployeeJoiningDate(Employee $employee, string $date, string $module = '', string $field = 'message')
    {
        if ($employee->joining_date->value > $date) {
            throw ValidationException::withMessages([$field => trans('validation.after_or_equal', ['attribute' => $module, 'date' => trans('employee.props.joining_date').' '.$employee->joining_date->formatted])]);
        }
    }

    public function validateEmployeeLeavingDate(Employee $employee, string $date, string $module = '', string $field = 'message')
    {
        if ($employee->leaving_date->value && $employee->leaving_date->value < $date) {
            throw ValidationException::withMessages([$field => trans('validation.before_or_equal', ['attribute' => $module, 'date' => trans('employee.props.joining_date').' '.$employee->leaving_date->value])]);
        }
    }
}
