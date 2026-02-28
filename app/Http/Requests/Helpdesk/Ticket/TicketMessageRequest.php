<?php

namespace App\Http\Requests\Helpdesk\Ticket;

use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Models\Tenant\Helpdesk\Ticket\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TicketMessageRequest extends FormRequest
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
            'status' => ['required', new Enum(TicketStatus::class)],
            'message' => ['required', 'max:1000'],
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new Ticket)->getModelName();

            $ticketUuid = $this->route('ticket');

            $this->merge([]);
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
            'status' => __('helpdesk.ticket.props.status'),
            'message' => __('helpdesk.ticket.props.message'),
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
            'message.required' => trans('validation.required', ['attribute' => trans('helpdesk.ticket.props.message')]),
        ];
    }
}
