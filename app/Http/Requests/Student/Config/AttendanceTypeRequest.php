<?php

namespace App\Http\Requests\Student\Config;

use App\Enums\OptionType;
use App\Models\Tenant\Option;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceTypeRequest extends FormRequest
{
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
            'name' => 'required|min:1|max:100',
            'code' => 'required|min:1|max:5',
            'color' => ['sometimes', 'required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
            'sub_type' => 'required|in:present,absent',
            'description' => 'nullable|max:500',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('attendance_type.uuid');

            if (in_array(strtolower($this->code), ['p', 'a', 'h'])) {
                $validator->errors()->add('code', trans('validation.reserved', ['attribute' => __('student.attendance_type.props.code')]));
            }

            if (in_array(strtolower($this->name), ['present', 'absent', 'holiday'])) {
                $validator->errors()->add('name', trans('validation.reserved', ['attribute' => __('student.attendance_type.props.name')]));
            }

            $existingAttendanceType = Option::query()
                ->byTeam()
                ->whereIn('type', [OptionType::STUDENT_ATTENDANCE_TYPE])
                ->where('name', $this->name)
                ->when($uuid, function ($q) use ($uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->exists();

            if ($existingAttendanceType) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('student.attendance_type.props.name')]));
            }

            $existingAttendanceType = Option::query()
                ->byTeam()
                ->whereIn('type', [OptionType::STUDENT_ATTENDANCE_TYPE])
                ->where('meta->code', $this->code)
                ->when($uuid, function ($q) use ($uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->exists();

            if ($existingAttendanceType) {
                $validator->errors()->add('code', __('validation.unique', ['attribute' => __('student.attendance_type.props.name')]));
            }
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
            'name' => __('student.attendance_type.props.name'),
            'code' => __('student.attendance_type.props.code'),
            'color' => __('student.attendance_type.props.color'),
            'sub_type' => __('student.attendance_type.props.sub_type'),
            'description' => __('student.attendance_type.props.description'),
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
            'alert_days_before_expiry.required_if' => __('validation.required', ['attribute' => __('student.attendance_type.props.alert_days_before_expiry')]),
        ];
    }
}
