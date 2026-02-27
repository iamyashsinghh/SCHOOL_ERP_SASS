<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\FeeInstallmentRecord;
use App\Models\Finance\FeeStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class FeeStructureComponentRequest extends FormRequest
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
            'fee_structure.uuid' => ['required', 'uuid'],
            'fee_head.uuid' => ['required', 'uuid'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.uuid' => ['required', 'uuid'],
            'components.*.amount' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            // $uuid = $this->route('fee_head');

            $feeStructureUuid = $this->input('fee_structure.uuid');
            $feeHeadUuid = $this->input('fee_head.uuid');

            $feeStructure = FeeStructure::query()
                ->byPeriod()
                ->where('uuid', $feeStructureUuid)
                ->getOrFail(trans('finance.fee_structure.fee_structure'), 'fee_structure');

            $feeInstallmentRecord = FeeInstallmentRecord::query()
                ->with('head.components')
                ->whereHas('installment', function ($query) use ($feeStructure) {
                    $query->where('fee_structure_id', $feeStructure->id);
                })
                ->where('uuid', $feeHeadUuid)
                ->getOrFail(trans('finance.fee_head.fee_head'), 'fee_head');

            if ($feeInstallmentRecord->amount->value == 0) {
                $validator->errors()->add('fee_head', trans('general.errors.invalid_input'));
            }

            $feeHead = $feeInstallmentRecord->head;

            if ($feeHead->components->count() !== count($this->components)) {
                $validator->errors()->add('message', trans('general.errors.invalid_input'));
            }

            $newComponents = [];
            foreach ($this->components as $index => $component) {
                $feeComponent = $feeHead->components->where('uuid', Arr::get($component, 'uuid'))->first();

                if (! $feeComponent) {
                    $validator->errors()->add('components.'.$index.'.amount', trans('general.errors.invalid_input'));
                }

                $component['id'] = $feeComponent->id;

                $newComponents[] = Arr::only($component, ['id', 'amount']);
            }

            if (collect($newComponents)->sum('amount') != $feeInstallmentRecord->amount->value) {
                $validator->errors()->add('fee_head', trans('finance.fee_structure.component_amount_mismatch'));
            }

            $this->merge([
                'fee_structure_id' => $feeStructure?->id,
                'fee_installment_record_id' => $feeInstallmentRecord?->id,
                'fee_head_id' => $feeHead?->id,
                'components' => $newComponents,
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
            'fee_structure' => __('finance.fee_structure.fee_structure'),
            'fee_head' => __('finance.fee_head.fee_head'),
            'components' => __('finance.fee_component.fee_components'),
            'components.*.uuid' => __('finance.fee_component.fee_component'),
            'components.*.amount' => __('finance.fee_structure.props.amount'),
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
