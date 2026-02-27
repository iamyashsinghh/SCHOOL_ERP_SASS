<?php

namespace App\Http\Requests\Task;

use App\Enums\Day;
use App\Enums\RepeatFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class RepeatRequest extends FormRequest
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
            'should_repeat' => 'boolean',
        ];

        if ($this->should_repeat) {
            $rules['start_date'] = 'required|date|before:end_date';
            $rules['end_date'] = 'required|date';
            $rules['frequency'] = ['required', new Enum(RepeatFrequency::class)];

            if ($this->frequency == 'day_wise') {
                $rules['days'] = 'required|array|min:1';
            } elseif ($this->frequency == 'date_wise') {
                $rules['dates'] = 'required|array|min:1';
            } elseif ($this->frequency == 'day_wise_count') {
                $rules['day_wise_count'] = 'required|integer|min:1|max:31';
            }
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('task');

            if (! $this->should_repeat) {
                return;
            }

            if ($this->frequency == 'day_wise' && array_diff($this->days, Day::getKeys())) {
                $validator->errors()->add('frequency', trans('general.errors.invalid_input'));
            } elseif ($this->frequency == 'date_wise' && Arr::where($this->dates, function ($date) {
                return ! is_numeric($date) || (is_numeric($date) && ($date < 1 || $date > 31));
            })) {
                $validator->errors()->add('frequency', trans('general.errors.invalid_input'));
            }

            $this->merge(['days' => array_unique($this->days)]);

            if ($this->frequency == 'date_wise') {
                $dates = Arr::map($this->dates, function ($item) {
                    return (int) $item;
                });

                $this->merge(['dates' => array_unique($dates)]);
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
            'should_repeat' => __('task.repeat.props.should_repeat'),
            'start_date' => __('task.repeat.props.start_date'),
            'end_date' => __('task.repeat.props.end_date'),
            'frequency' => __('task.repeat.props.frequency'),
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
