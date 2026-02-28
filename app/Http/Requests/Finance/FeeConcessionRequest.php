<?php

namespace App\Http\Requests\Finance;

use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Finance\FeeHead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class FeeConcessionRequest extends FormRequest
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
            'name' => 'required|min:3|max:100',
            'code' => 'nullable|min:1|max:50',
            'transport_type' => 'required|in:percent,amount',
            'transport_value' => 'required|numeric|min:0',
            'transport_secondary_type' => 'nullable|in:percent,amount',
            'transport_secondary_value' => 'nullable|numeric|min:0',
            'records' => 'array|required|min:1',
            'records.*.head' => 'required|distinct',
            'records.*.type' => 'required|in:percent,amount',
            'records.*.value' => 'required|numeric|min:0',
            'records.*.secondary_type' => 'nullable|in:percent,amount',
            'records.*.secondary_value' => 'nullable|numeric|min:0',
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
            $uuid = $this->route('fee_concession');

            $existingRecords = FeeConcession::query()
                ->byPeriod()->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })->whereName($this->name)->count();

            if ($existingRecords) {
                $validator->errors()->add('name', trans('validation.unique', ['attribute' => trans('finance.fee_concession.fee_concession')]));
            }

            if ($this->code) {
                $existingCode = FeeConcession::query()
                    ->byPeriod()->when($uuid, function ($q, $uuid) {
                        $q->where('uuid', '!=', $uuid);
                    })->where('meta->code', $this->code)->count();

                if ($existingCode) {
                    $validator->errors()->add('code', trans('validation.unique', ['attribute' => trans('finance.fee_concession.props.code')]));
                }
            }

            if ($this->transport_type == 'percent' && $this->transport_value > 100) {
                $validator->errors()->add('transport_value', trans('validation.lte.numeric', ['attribute' => trans('transport.fee.fee'), 'value' => 100]));
            }

            if ($this->transport_secondary_type == 'percent' && $this->transport_secondary_value > 100) {
                $validator->errors()->add('transport_secondary_value', trans('validation.lte.numeric', ['attribute' => trans('transport.fee.fee'), 'value' => 100]));
            }

            if ($this->transport_value == 0 && $this->transport_secondary_value > 0) {
                $validator->errors()->add('transport_value', trans('validation.required_if', ['attribute' => trans('finance.fee_concession.props.transport_value'), 'other' => trans('finance.fee_concession.secondary_concession'), 'value' => '> 0']));
            }

            $feeHeads = FeeHead::query()
                ->byPeriod()
                ->select('id', 'uuid')
                ->get();

            $feeHeadUuids = $feeHeads->pluck('uuid')->all();

            $newRecords = [];
            foreach ($this->records as $index => $record) {
                $uuid = Arr::get($record, 'head.uuid');

                $type = Arr::get($record, 'type');
                $value = Arr::get($record, 'value', 0);
                $secondaryType = Arr::get($record, 'secondary_type');
                $secondaryValue = Arr::get($record, 'secondary_value', 0);

                if ($value == 0 && $secondaryValue > 0) {
                    $validator->errors()->add('records.'.$index.'.value', trans('validation.required_if', ['attribute' => trans('finance.fee_concession.props.value'), 'other' => trans('finance.fee_concession.secondary_concession'), 'value' => '> 0']));
                }

                if (! in_array($uuid, $feeHeadUuids)) {
                    $validator->errors()->add('records.'.$index.'.head', trans('validation.exists', ['attribute' => trans('finance.fee_head.fee_head')]));
                } elseif ($type == 'percent' && $value > 100) {
                    $validator->errors()->add('records.'.$index.'.value', trans('validation.lte.numeric', ['attribute' => trans('finance.fee_concession.props.value'), 'value' => 100]));
                } elseif ($secondaryType == 'percent' && $secondaryValue > 100) {
                    $validator->errors()->add('records.'.$index.'.secondary_value', trans('validation.lte.numeric', ['attribute' => trans('finance.fee_concession.props.value'), 'value' => 100]));
                } else {
                    if ($value > 0 || $secondaryValue > 0) {
                        $newRecords[] = Arr::add($record, 'head.id', $feeHeads->firstWhere('uuid', $uuid)->id);
                    }
                }
            }

            $this->merge(['records' => $newRecords]);
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
            'name' => __('finance.fee_concession.props.name'),
            'code' => __('finance.fee_concession.props.code'),
            'transport_type' => __('finance.fee_concession.props.transport_type'),
            'transport_value' => __('finance.fee_concession.props.transport_value'),
            'transport_secondary_type' => __('finance.fee_concession.props.transport_type'),
            'transport_secondary_value' => __('finance.fee_concession.props.transport_value'),
            'description' => __('finance.fee_concession.props.description'),
            'records.*.head' => __('finance.fee_head.fee_head'),
            'records.*.value' => __('finance.fee_concession.props.value'),
            'records.*.type' => __('finance.fee_concession.props.type'),
            'records.*.secondary_type' => __('finance.fee_concession.props.type'),
            'records.*.secondary_value' => __('finance.fee_concession.props.value'),
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
