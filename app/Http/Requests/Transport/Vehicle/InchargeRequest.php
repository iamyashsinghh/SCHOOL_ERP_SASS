<?php

namespace App\Http\Requests\Transport\Vehicle;

use App\Concerns\HasIncharge;
use App\Enums\Transport\Vehicle\InchargeType;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InchargeRequest extends FormRequest
{
    use HasIncharge;

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
            'vehicle' => 'required|uuid',
            'employee' => 'required|uuid',
            'type' => ['required', new Enum(InchargeType::class)],
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'remarks' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('incharge');

            $vehicle = Vehicle::query()
                ->byTeam()
                ->where('uuid', $this->vehicle)
                ->getOrFail(trans('transport.vehicle.vehicle'), 'vehicle');

            $employee = Employee::query()
                ->byTeam()
                ->where('uuid', $this->employee)
                ->getOrFail(trans('employee.employee'), 'employee');

            $this->validateInput(employee: $employee, model: $vehicle, uuid: $uuid, params: [
                'type' => $this->type,
            ]);

            $this->merge([
                'vehicle_id' => $vehicle->id,
                'employee_id' => $employee->id,
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
            'vehicle' => __('transport.vehicle.vehicle'),
            'type' => __('transport.vehicle.incharge.type'),
            'employee' => __('employee.employee'),
            'start_date' => __('employee.incharge.props.start_date'),
            'end_date' => __('employee.incharge.props.end_date'),
            'remarks' => __('employee.incharge.props.remarks'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }
}
