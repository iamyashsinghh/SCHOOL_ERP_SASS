<?php

namespace App\Http\Requests\Employee\Config;

use App\Enums\OptionType;
use App\Models\Option;
use App\Rules\SafeRegex;
use Illuminate\Foundation\Http\FormRequest;

class DocumentTypeRequest extends FormRequest
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
            'color' => ['sometimes', 'required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
            'has_number' => 'boolean',
            'number_format' => ['nullable', 'string', new SafeRegex],
            'has_expiry_date' => 'boolean',
            'alert_days_before_expiry' => 'required_if:has_expiry_date,true|integer|min:0',
            'is_document_required' => 'boolean',
            'description' => 'nullable|max:500',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('document_type.uuid');

            $existingDocumentType = Option::query()
                ->byTeam()
                ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
                ->where('name', $this->name)
                ->when($uuid, function ($q) use ($uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->exists();

            if ($existingDocumentType) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('employee.document_type.props.name')]));
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
            'name' => __('employee.document_type.props.name'),
            'color' => __('option.props.color'),
            'has_number' => __('employee.document_type.props.number'),
            'number_format' => __('employee.document_type.props.number_format'),
            'has_expiry_date' => __('employee.document_type.props.expiry_date'),
            'alert_days_before_expiry' => __('employee.document_type.props.alert_days_before_expiry'),
            'is_document_required' => __('global.required', ['attribute' => __('employee.document.document')]),
            'description' => __('employee.document_type.props.description'),
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
            'alert_days_before_expiry.required_if' => __('validation.required', ['attribute' => __('employee.document_type.props.alert_days_before_expiry')]),
        ];
    }
}
