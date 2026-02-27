<?php

namespace App\Http\Requests\Exam;

use App\Models\Exam\Competency;
use App\Models\Exam\Grade;
use Illuminate\Foundation\Http\FormRequest;

class CompetencyRequest extends FormRequest
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
            'name' => ['required', 'min:2', 'max:100'],
            'grade' => 'required|uuid',
            'domains' => 'required|array|min:1',
            'domains.*.name' => 'required|min:1|max:50|distinct',
            'domains.*.code' => 'required|min:1|max:10|distinct',
            'domains.*.indicators' => 'required|array|min:1',
            'domains.*.indicators.*.name' => 'required|min:1|max:200|distinct',
            'domains.*.indicators.*.code' => 'required|min:1|max:50|distinct',
            'description' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('competency');

            $grade = Grade::query()
                ->byPeriod()
                ->whereUuid($this->grade)
                ->getOrFail(trans('exam.grade.grade'), 'grade');

            $existingRecords = Competency::query()
                ->byPeriod()
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->whereName($this->name)
                ->exists();

            if ($existingRecords) {
                $validator->errors()->add('name', trans('validation.unique', ['attribute' => __('exam.competency.props.name')]));
            }

            $this->merge([
                'grade_id' => $grade->id,
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
            'name' => __('exam.competency.props.name'),
            'grade' => __('exam.grade.grade'),
            'domains' => __('exam.competency.props.domain'),
            'domains.*.name' => __('exam.competency.props.name'),
            'domains.*.code' => __('exam.competency.props.code'),
            'domains.*.indicators' => __('exam.competency.props.indicator'),
            'domains.*.indicators.*.name' => __('exam.competency.props.name'),
            'domains.*.indicators.*.code' => __('exam.competency.props.code'),
            'description' => __('exam.competency.props.description'),
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
