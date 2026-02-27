<?php

namespace App\Http\Requests\Helpdesk\Ticket;

use App\Concerns\CustomFormFieldValidation;
use App\Enums\CustomFieldForm;
use App\Enums\OptionType;
use App\Models\CustomField;
use App\Models\Option;
use Illuminate\Foundation\Http\FormRequest;

class TicketRequest extends FormRequest
{
    use CustomFormFieldValidation;

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
            'title' => 'required|min:2|max:200',
            'category' => 'required|uuid',
            'priority' => 'required|uuid',
            'description' => 'min:2|max:10000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('ticket');

            $category = $this->category ? Option::byTeam()->whereType(OptionType::TICKET_CATEGORY)->whereUuid($this->category)->getOrFail(trans('helpdesk.ticket.category.category'), 'category') : null;

            $priority = $this->priority ? Option::byTeam()->whereType(OptionType::TICKET_PRIORITY)->whereUuid($this->priority)->getOrFail(trans('helpdesk.ticket.priority.priority'), 'priority') : null;

            $this->merge([
                'category_id' => $category?->id,
                'priority_id' => $priority?->id,
            ]);

            $customFields = CustomField::query()
                ->byTeam()
                ->whereForm(CustomFieldForm::TICKET)
                ->get();

            $newCustomFields = $this->validateFields($validator, $customFields, $this->input('custom_fields', []));

            $this->merge([
                'custom_fields' => $newCustomFields,
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
            'title' => __('helpdesk.ticket.props.title'),
            'category' => __('helpdesk.ticket.category.category'),
            'priority' => __('helpdesk.ticket.priority.priority'),
            'description' => __('helpdesk.ticket.props.description'),
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
