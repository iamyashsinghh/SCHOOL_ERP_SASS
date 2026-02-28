<?php

namespace App\Actions\Config;

use App\Actions\SendWhatsApp;
use App\Models\Tenant\Config\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TestWhatsApp
{
    public function execute(Request $request)
    {
        $testWhatsAppTemplate = Template::query()
            ->whereType('whatsapp')
            ->where('code', 'test-whatsapp-notification')
            ->firstOrFail();

        $params = [
            'template_id' => $testWhatsAppTemplate->getMeta('template_id'),
            'recipients' => [
                [
                    'mobile' => config('config.whatsapp.test_number'),
                    'message' => $testWhatsAppTemplate->content,
                    'variables' => [
                        'name' => auth()->user()?->name,
                    ],
                ],
            ],
        ];

        $response = (new SendWhatsApp)->execute($params);

        if (is_array($response) && Arr::get($response, 'status') != 200) {
            throw ValidationException::withMessages([
                'message' => Arr::get($response, 'message', trans('config.whatsapp.error_sending_message')),
            ]);
        }

        // defer(function () use ($params) {
        //     (new SendWhatsApp)->execute($params);
        // });
    }
}
