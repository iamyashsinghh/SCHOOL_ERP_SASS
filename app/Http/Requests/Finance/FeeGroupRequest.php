<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\FeeGroup;
use Illuminate\Foundation\Http\FormRequest;

class FeeGroupRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'code' => ['nullable', 'string', 'min:1', 'max:50'],
            'shortcode' => ['nullable', 'string', 'min:1', 'max:50'],
            'pg_account' => ['nullable', 'max:100'],
            'description' => 'nullable|string|max:1000',
        ];

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('fee_group');

            $existingRecords = FeeGroup::query()
                ->byPeriod()
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->whereName($this->name)
                ->exists();

            if ($existingRecords) {
                $validator->errors()->add('name', trans('validation.unique', ['attribute' => trans('finance.fee_group.fee_group')]));
            }

            $this->whenFilled('code', function (string $input) use ($validator, $uuid) {
                $existingCodes = FeeGroup::query()
                    ->byPeriod()
                    ->when($uuid, function ($q, $uuid) {
                        $q->where('uuid', '!=', $uuid);
                    })
                    ->whereCode($input)
                    ->exists();

                if ($existingCodes) {
                    $validator->errors()->add('code', trans('validation.unique', ['attribute' => trans('finance.fee_group.fee_group')]));
                }
            });

            $this->whenFilled('shortcode', function (string $input) {
                // $existingShortcodes = FeeGroup::query()
                //     ->byPeriod()
                //     ->when($uuid, function ($q, $uuid) {
                //         $q->where('uuid', '!=', $uuid);
                //     })
                //     ->whereShortcode($input)
                //     ->exists();

                // if ($existingShortcodes) {
                //     $validator->errors()->add('shortcode', trans('validation.unique', ['attribute' => trans('finance.fee_group.fee_group')]));
                // }
            });
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
            'name' => __('finance.fee_group.props.name'),
            'pg_account' => __('finance.config.props.pg_account'),
            'description' => __('finance.fee_group.props.description'),
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
