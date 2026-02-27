<?php

namespace App\Http\Requests\Task;

use App\Concerns\SubordinateAccess;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Http\FormRequest;

class MemberRequest extends FormRequest
{
    use SubordinateAccess;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'employees' => 'array',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $taskUuid = $this->route('task');
            $memberUuid = $this->route('member');

            $employee = Employee::auth()->first();

            $employees = Employee::query()
                ->whereHas('contact', function ($q) use ($employee) {
                    $q->where('team_id', $employee?->team_id ?? auth()->user()->current_team_id);
                })
                ->whereIn('uuid', $this->employees)
                ->get();

            // $this->validateAccessibleEmployees($employees);

            $this->merge([
                'employee_ids' => $employees->pluck('id')->all(),
            ]);
        });
    }

    /**
     * Translate fields with user friendly name.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'employee' => __('employee.employee'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }
}
