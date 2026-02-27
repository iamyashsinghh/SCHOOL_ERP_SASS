<?php

namespace App\Actions;

use App\Services\Config\SMSGateway\Gateway;
use App\Support\TemplateParser;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SendSMS
{
    use TemplateParser;

    public function execute(array $params = []): void
    {
        $recipients = Arr::get($params, 'recipients', []);

        if (! count($recipients)) {
            throw ValidationException::withMessages(['message' => trans('communication.sms.no_recipient_found')]);
        }

        if (! Arr::get($params, 'validate_driver', false) && empty(config('config.sms.driver'))) {
            return;
        }

        $gateway = Gateway::init();

        if (count($recipients) == 1) {
            $variables = Arr::get($recipients[0], 'variables', []);
            $variables = $this->addGeneralVariable($variables);

            $gateway->sendSMS(recipient: $recipients[0], params: [
                'template_id' => Arr::get($params, 'template_id'),
                'variables' => $variables,
            ]);

            return;
        }

        $message = collect($recipients)->unique('message')->count();

        if ($message == 1) {
            $gateway->sendBulkSMS(recipients: $recipients, params: [
                'template_id' => Arr::get($params, 'template_id'),
            ]);

            return;
        }

        $gateway->sendCustomizedSMS(recipients: $recipients, params: [
            'template_id' => Arr::get($params, 'template_id'),
        ]);
    }
}
