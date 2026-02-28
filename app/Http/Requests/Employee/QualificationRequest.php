<?php

namespace App\Http\Requests\Employee;

use App\Enums\OptionType;
use App\Enums\QualificationResult;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Option;
use App\Models\Tenant\Qualification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class QualificationRequest extends FormRequest
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
            'level' => 'required',
            'course' => 'required|min:2|max:100',
            'session' => 'nullable|min:2|max:100',
            'institute' => 'nullable|min:2|max:100',
            'institute_address' => 'nullable|min:2|max:100',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'affiliated_to' => 'nullable|min:2|max:100',
            'result' => 'nullable|min:2|max:200',
            'result' => ['nullable', new Enum(QualificationResult::class)],
            'total_marks' => 'required_if:result,pass|numeric|min:0|max:10000',
            'obtained_marks' => 'required_if:result,pass|numeric|min:0|max:10000|lte:total_marks',
            'failed_subjects' => 'required_if:result,reappear|min:2|max:200',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $employeeUuid = $this->route('employee');
            $qualificationUuid = $this->route('qualification');

            $employee = Employee::query()
                ->byTeam()
                ->whereUuid($employeeUuid)
                ->firstOrFail();

            $qualificationLevel = Option::query()
                ->byTeam()
                ->whereType(OptionType::QUALIFICATION_LEVEL->value)
                ->whereUuid($this->level)
                ->getOrFail(__('contact.qualification_level.qualification_level'), 'level');

            $existingQualification = Qualification::whereHasMorph(
                'model', [Contact::class],
                function ($q) use ($employee) {
                    $q->whereId($employee->contact_id);
                }
            )
                ->when($qualificationUuid, function ($q, $qualificationUuid) {
                    $q->where('uuid', '!=', $qualificationUuid);
                })
                ->whereCourse($this->course)
                ->exists();

            if ($existingQualification) {
                $validator->errors()->add('course', trans('validation.unique', ['attribute' => __('employee.qualification.props.course')]));
            }

            $this->merge([
                'level_id' => $qualificationLevel->id,
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
            'course' => __('employee.qualification.props.course'),
            'session' => __('employee.qualification.props.session'),
            'institute_address' => __('employee.qualification.props.institute_address'),
            'institute' => __('employee.qualification.props.institute'),
            'start_date' => __('employee.qualification.props.start_date'),
            'end_date' => __('employee.qualification.props.end_date'),
            'affiliated_to' => __('employee.qualification.props.affiliated_to'),
            'result' => __('employee.qualification.props.result'),
            'total_marks' => __('employee.qualification.props.total_marks'),
            'obtained_marks' => __('employee.qualification.props.obtained_marks'),
            'failed_subjects' => __('employee.qualification.props.failed_subjects'),
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
