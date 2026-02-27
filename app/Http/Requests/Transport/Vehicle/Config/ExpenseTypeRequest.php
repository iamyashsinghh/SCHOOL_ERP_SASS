<?php

namespace App\Http\Requests\Transport\Vehicle\Config;

use App\Enums\OptionType;
use App\Models\Option;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseTypeRequest extends FormRequest
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
            'has_reminder' => 'boolean',
            'has_quantity' => 'boolean',
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
            $uuid = $this->route('expense_type.uuid');

            $existingExpenseType = Option::query()
                ->byTeam()
                ->where('type', OptionType::VEHICLE_EXPENSE_TYPE)
                ->where('name', $this->name)
                ->when($uuid, function ($q) use ($uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->exists();

            if ($existingExpenseType) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('transport.vehicle.expense_type.props.name')]));
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
            'name' => __('transport.vehicle.expense_type.props.name'),
            'color' => __('option.props.color'),
            'has_reminder' => __('reminder.reminder'),
            'has_quantity' => __('transport.vehicle.expense_type.props.quantity'),
            'is_document_required' => __('transport.vehicle.expense_type.props.document'),
            'description' => __('transport.vehicle.expense_type.props.description'),
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
