<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\CreateCustomFeeHead;
use App\Enums\Finance\DefaultCustomFeeType;
use App\Models\Finance\FeeGroup;
use App\Models\Finance\FeeHead;
use App\Models\Finance\Tax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class FeeHeadRequest extends FormRequest
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
            'fee_group' => 'nullable|uuid',
            'type' => ['nullable', new Enum(DefaultCustomFeeType::class)],
            'tax' => 'nullable|uuid',
            'tax_type' => 'nullable|string|in:inclusive,exclusive',
            'hsn_code' => 'nullable|string|max:20',
            'components' => 'nullable|array',
            'components.*.uuid' => 'required_if:has_components,true|uuid|distinct',
            'components.*.name' => 'required_if:has_components,true|string|max:100|distinct',
            'components.*.tax' => 'nullable|uuid',
            'components.*.tax_type' => 'nullable|string|in:inclusive,exclusive',
            'components.*.hsn_code' => 'nullable|string|max:20',
            'description' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('fee_head');

            if (in_array(strtolower($this->name), ['transport fee', 'late fee', 'additional charge', 'additional discount', 'registration fee'])) {
                $validator->errors()->add('name', trans('finance.fee_head.reserved_name'));
            }

            $feeGroup = $this->fee_group ? FeeGroup::query()
                ->byPeriod()
                ->where('uuid', $this->fee_group)
                ->getOrFail(trans('finance.fee_group.fee_group'), 'fee_group') : (new CreateCustomFeeHead)->execute();

            $existingRecords = FeeHead::query()
                ->when($this->fee_group, function ($q) use ($feeGroup) {
                    $q->whereFeeGroupId($feeGroup->id);
                })
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->wherePeriodId(auth()->user()->current_period_id)
                ->whereName($this->name)
                ->exists();

            if ($existingRecords) {
                $validator->errors()->add('name', trans('validation.unique', ['attribute' => __('finance.fee_head.props.name')]));
            }

            if ((! $this->fee_group || $feeGroup?->is_custom) && $this->type) {
                $existingFeeType = FeeHead::query()
                    ->where('type', $this->type)
                    ->wherePeriodId(auth()->user()->current_period_id)
                    ->when($uuid, function ($q, $uuid) {
                        $q->where('uuid', '!=', $uuid);
                    })
                    ->exists();

                if ($existingFeeType) {
                    $validator->errors()->add('type', trans('validation.unique', ['attribute' => __('finance.fee_head.props.type')]));
                }
            } else {
                $this->merge(['type' => null]);
            }

            if (! $this->has_components && $this->tax) {
                $tax = Tax::query()
                    ->byTeam()
                    ->where('uuid', $this->tax)
                    ->getOrFail(trans('finance.tax.tax'), 'tax');

                $this->merge([
                    'tax_id' => $tax?->id,
                ]);
            } elseif ($this->has_components) {
                if (count($this->components) == 0) {
                    $validator->errors()->add('message', trans('validation.required', ['attribute' => __('finance.fee_head.props.components')]));
                }

                $taxes = Tax::query()
                    ->byTeam()
                    ->whereIn('uuid', collect($this->components)->pluck('tax'))
                    ->get();

                $newComponents = [];
                foreach ($this->components as $component) {
                    $tax = null;
                    if (Arr::get($component, 'tax')) {
                        $tax = $taxes->firstWhere('uuid', Arr::get($component, 'tax'));

                        if (! $tax) {
                            $validator->errors()->add('components.*.tax', trans('global.could_not_find', ['attribute' => trans('finance.tax.tax')]));
                        }
                    }

                    $component['tax_id'] = $tax?->id;

                    $newComponents[] = Arr::only($component, ['uuid', 'name', 'hsn_code', 'tax_type', 'tax_id']);
                }

                $this->merge(['components' => $newComponents]);
            }

            $this->whenFilled('code', function (string $input) use ($validator, $feeGroup, $uuid) {
                $existingCodes = FeeHead::query()
                    ->byPeriod()
                    ->when($uuid, function ($q, $uuid) {
                        $q->where('uuid', '!=', $uuid);
                    })
                    ->when($feeGroup, function ($q) use ($feeGroup) {
                        $q->whereFeeGroupId($feeGroup->id);
                    })
                    ->whereCode($input)
                    ->exists();

                if ($existingCodes) {
                    $validator->errors()->add('code', trans('validation.unique', ['attribute' => trans('finance.fee_head.fee_head')]));
                }
            });

            $this->whenFilled('shortcode', function (string $input) {
                // $existingShortcodes = FeeHead::query()
                //     ->byPeriod()
                //     ->when($uuid, function ($q, $uuid) {
                //         $q->where('uuid', '!=', $uuid);
                //     })
                //     ->when($feeGroup, function ($q) use ($feeGroup) {
                //         $q->whereFeeGroupId($feeGroup->id);
                //     })
                //     ->whereShortcode($input)
                //     ->exists();

                // if ($existingShortcodes) {
                //     $validator->errors()->add('shortcode', trans('validation.unique', ['attribute' => trans('finance.fee_head.fee_head')]));
                // }
            });

            $this->merge(['fee_group_id' => $feeGroup?->id]);
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
            'name' => __('finance.fee_head.props.name'),
            'fee_group' => __('finance.fee_group.fee_group'),
            'type' => __('finance.fee_head.props.type'),
            'tax' => __('finance.tax.tax'),
            'tax_type' => __('finance.tax.props.type'),
            'hsn_code' => __('finance.tax.props.hsn_code'),
            'components' => __('finance.fee_head.props.components'),
            'components.*.uuid' => __('finance.fee_head.props.components'),
            'components.*.name' => __('finance.fee_head.props.components'),
            'components.*.tax' => __('finance.tax.tax'),
            'description' => __('finance.fee_head.props.description'),
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
            'components.*.uuid.required_if' => trans('validation.required', ['attribute' => __('finance.fee_head.props.components')]),
            'components.*.name.required_if' => trans('validation.required', ['attribute' => __('finance.fee_head.props.components')]),
        ];
    }
}
