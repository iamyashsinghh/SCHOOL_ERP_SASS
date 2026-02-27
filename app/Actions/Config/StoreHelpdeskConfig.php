<?php

namespace App\Actions\Config;

class StoreHelpdeskConfig
{
    public static function handle(): array
    {
        $input = request()->validate([
            'enable_faq' => 'sometimes|boolean',
            'faq_title' => 'sometimes|string',
            'faq_description' => 'sometimes|string',
            'ticket_number_prefix' => 'sometimes|string',
            'ticket_number_digit' => 'sometimes|integer',
            'ticket_number_suffix' => 'sometimes|string',
        ], [
        ], [
            'enable_faq' => __('global.enable', ['attribite' => trans('helpdesk.faq.faq')]),
            'faq_title' => __('helpdesk.faq.config.props.title'),
            'faq_description' => __('helpdesk.faq.config.props.description'),
            'ticket_number_prefix' => __('helpdesk.ticket.config.props.number_prefix'),
            'ticket_number_digit' => __('helpdesk.ticket.config.props.number_digit'),
            'ticket_number_suffix' => __('helpdesk.ticket.config.props.number_suffix'),
        ]);

        return $input;
    }
}
