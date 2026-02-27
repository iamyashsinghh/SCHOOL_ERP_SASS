<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\TaxType;
use App\Models\Finance\FeeComponent;
use App\Models\Finance\FeeHead;
use App\Models\Finance\Tax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class FeeComponentRequest extends FormRequest
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
            'name' => ['required', 'min:1', 'max:100'],
            'fee_head' => ['required', 'uuid'],
            'tax' => ['nullable', 'uuid'],
            'tax_type' => ['nullable', 'string', new Enum(TaxType::class)],
            'rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('fee_component');

            $feeHead = FeeHead::query()
                ->byPeriod()
                ->where('uuid', $this->fee_head)
                ->getOrFail(trans('finance.fee_head.fee_head'), 'fee_head');

            $tax = $this->tax ? Tax::query()
                ->byTeam()
                ->where('uuid', $this->tax)
                ->getOrFail(trans('finance.tax.tax'), 'tax') : null;

            $existingRecord = FeeComponent::query()
                ->byPeriod()
                ->when($uuid, function ($query) use ($uuid) {
                    $query->where('uuid', '!=', $uuid);
                })
                ->where('name', $this->name)
                ->exists();

            if ($existingRecord) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('finance.fee_component.props.name')]));
            }

            $this->merge([
                'fee_head_id' => $feeHead?->id,
                'tax_id' => $tax?->id,
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
            'name' => __('finance.fee_component.props.name'),
            'fee_head' => __('finance.fee_head.fee_head'),
            'tax' => __('finance.tax.tax'),
            'tax_type' => __('finance.tax.props.type'),
            'rate' => __('finance.tax.props.rate'),
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
