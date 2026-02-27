<?php

namespace App\Actions;

use App\Services\Config\WhatsAppProvider\Provider;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SendWhatsApp
{
    public function execute(array $params = []): mixed
    {
        $recipients = Arr::get($params, 'recipients', []);

        if (! count($recipients)) {
            throw ValidationException::withMessages(['message' => trans('communication.whatsapp.no_recipient_found')]);
        }

        $provider = Provider::init();

        if (count($recipients) == 1) {
            return $provider->sendWhatsApp(recipient: $recipients[0], params: [
                'template_id' => Arr::get($params, 'template_id'),
                'template_code' => Arr::get($params, 'template_code'),
                'variables' => Arr::get($recipients[0], 'variables', []),
            ]);
        }

        $message = collect($recipients)->unique('message')->count();

        if ($message == 1) {
            return $provider->sendBulkWhatsApp(recipients: $recipients, params: [
                'template_id' => Arr::get($params, 'template_id'),
                'template_code' => Arr::get($params, 'template_code'),
            ]);
        }

        return $provider->sendCustomizedWhatsApp(recipients: $recipients, params: [
            'template_id' => Arr::get($params, 'template_id'),
            'template_code' => Arr::get($params, 'template_code'),
        ]);
    }
}
