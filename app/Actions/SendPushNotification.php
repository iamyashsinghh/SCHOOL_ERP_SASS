<?php

namespace App\Actions;

use App\Models\Tenant\Config\Template;
use App\Support\TemplateParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SendPushNotification
{
    use TemplateParser;

    public function execute(array $pushTokens = [], ?string $code = null, mixed $template = null, array $variables = [], array $params = [])
    {
        if (empty($pushTokens)) {
            return;
        }

        $pushTemplate = $template ?? Template::query()
            ->whereType('push')
            ->whereCode($code)
            ->whereNotNull('enabled_at')
            ->first();

        if (! $pushTemplate) {
            return;
        }

        $pushTemplate = $this->parseTemplate($pushTemplate, $variables);

        $messages = [];

        foreach ($pushTokens as $address) {
            $messages[] = [
                'sound' => 'default',
                'to' => $address,
                'title' => $pushTemplate->subject,
                'body' => $pushTemplate->content,
                'data' => (object) Arr::get($params, 'data', []),
            ];
        }

        // logger($messages);

        if ($messages) {
            $response = Http::post('https://exp.host/--/api/v2/push/send', $messages);

            // logger($response->body());
        }
    }
}
