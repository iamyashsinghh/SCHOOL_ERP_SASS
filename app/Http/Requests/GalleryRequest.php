<?php

namespace App\Http\Requests;

use App\Enums\Employee\AudienceType as EmployeeAudienceType;
use App\Enums\GalleryType;
use App\Enums\Student\AudienceType as StudentAudienceType;
use App\Models\Gallery;
use App\Support\HasAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class GalleryRequest extends FormRequest
{
    use HasAudience;

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
            'title' => 'required|string|max:255',
            'type' => ['required', new Enum(GalleryType::class)],
            'date' => 'required|date_format:Y-m-d',
            'student_audience_type' => [new Enum(StudentAudienceType::class)],
            'employee_audience_type' => [new Enum(EmployeeAudienceType::class)],
            'student_audiences' => 'array|required_if:student_audience_type,division_wise,course_wise,batch_wise',
            'employee_audiences' => 'array|required_if:employee_audience_type,department_wise,designation_wise',
            'excerpt' => 'nullable|min:2|max:255',
            'description' => 'nullable|min:2|max:10000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('gallery');

            $existingTitles = Gallery::query()
                ->byTeam()
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->whereTitle($this->title)
                ->exists();

            if ($existingTitles) {
                $validator->errors()->add('title', trans('validation.unique', ['attribute' => __('gallery.props.title')]));
            }

            $data = $this->validateInput($this->all());

            $this->merge([
                'student_audience_type' => Arr::get($data, 'studentAudienceType'),
                'employee_audience_type' => Arr::get($data, 'employeeAudienceType'),
                'student_audiences' => Arr::get($data, 'studentAudiences'),
                'employee_audiences' => Arr::get($data, 'employeeAudiences'),
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
            'title' => __('gallery.props.title'),
            'type' => __('gallery.props.type'),
            'date' => __('gallery.props.date'),
            'student_audience_type' => __('gallery.props.audience'),
            'employee_audience_type' => __('gallery.props.audience'),
            'student_audiences' => __('gallery.props.audience'),
            'employee_audiences' => __('gallery.props.audience'),
            'excerpt' => __('gallery.props.excerpt'),
            'description' => __('gallery.props.description'),
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
            'student_audiences.required_if' => __('validation.required', ['attribute' => trans('gallery.props.audience')]),
            'employee_audiences.required_if' => __('validation.required', ['attribute' => trans('gallery.props.audience')]),
        ];
    }
}
