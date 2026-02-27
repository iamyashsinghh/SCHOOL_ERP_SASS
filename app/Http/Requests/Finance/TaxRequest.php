<?php

namespace App\Http\Requests\Finance;

use App\Concerns\SimpleValidation;
use App\Models\Finance\Tax;
use Illuminate\Foundation\Http\FormRequest;

class TaxRequest extends FormRequest
{
    use SimpleValidation;

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
        $rules = [
            'name' => 'required|min:1|max:100',
            'code' => 'required|min:1|max:20',
            'rate' => 'required|numeric|min:0',
            'description' => 'nullable|max:1000',
            'has_components' => 'nullable|boolean',
            'components' => 'required_if:has_components,true|array',
            'components.*.name' => 'required_if:has_components,true|min:1|max:100|distinct',
            'components.*.code' => 'required_if:has_components,true|min:1|max:20|distinct',
            'components.*.rate' => 'required_if:has_components,true|numeric|min:0',
        ];

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('tax.uuid');

            if ($this->has_components) {
                $componentsRate = collect($this->components)->sum('rate');

                if ($componentsRate != $this->rate) {
                    $validator->errors()->add('rate', __('finance.tax.components_rate_mismatch'));
                }
            }

            $existingTaxes = Tax::query()
                ->byTeam()
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->where('name', $this->name)
                ->exists();

            if ($existingTaxes) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('finance.tax.props.name')]));
            }

            $existingTaxes = Tax::query()
                ->byTeam()
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->where('code', $this->code)
                ->exists();

            if ($existingTaxes) {
                $validator->errors()->add('code', __('validation.unique', ['attribute' => __('finance.tax.props.code')]));
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
            'name' => __('finance.tax.props.name'),
            'code' => __('finance.tax.props.code'),
            'rate' => __('finance.tax.props.rate'),
            'description' => __('finance.tax.props.description'),
            'has_components' => __('finance.tax.props.components'),
            'components' => __('finance.tax.props.components'),
            'components.*.name' => __('finance.tax.props.name'),
            'components.*.code' => __('finance.tax.props.code'),
            'components.*.rate' => __('finance.tax.props.rate'),
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
            'components.required_if' => __('validation.required', ['attribute' => __('finance.tax.props.components')]),
            'components.*.name.required_if' => __('validation.required', ['attribute' => __('finance.tax.props.name')]),
            'components.*.code.required_if' => __('validation.required', ['attribute' => __('finance.tax.props.code')]),
            'components.*.rate.required_if' => __('validation.required', ['attribute' => __('finance.tax.props.rate')]),
        ];
    }
}
