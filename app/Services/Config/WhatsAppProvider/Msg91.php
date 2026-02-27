<?php

namespace App\Services\Config\WhatsAppProvider;

use App\Contracts\WhatsAppService;
use App\Contracts\WhatsAppTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class Msg91 extends WhatsAppTemplate implements WhatsAppService
{
    private string $url;

    public function __construct()
    {
        $this->url = 'https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';
    }

    public function sendWhatsApp(array $recipient, array $params = []): mixed
    {
        $templateCode = Arr::get($params, 'template_code');
        $templateId = ! $templateCode ? $this->getTemplateId($params) : null;

        if (! $templateId && ! $templateCode) {
            return [];
        }

        return $this->callApi($this->url, [
            'mobile' => Arr::get($recipient, 'mobile'),
            'template_id' => $templateId,
            'template_code' => $templateCode,
            'template_message' => Arr::get($recipient, 'message'),
            'attachments' => Arr::get($recipient, 'attachments', []),
        ], Arr::get($params, 'variables'));
    }

    public function sendBulkWhatsApp(array $recipients, array $params = []): mixed
    {
        return [];

        // foreach (array_chunk($recipients, 20) as $chunk) {
        //     $mobile = Arr::get($chunk, 'mobile');

        //     if (config('config.whatsapp.number_prefix')) {
        //         $mobile = config('config.whatsapp.number_prefix').$mobile;
        //     }

        //     $this->callApi($this->url, [
        //         $this->receiverParam => $mobile,
        //         $this->messageParam => Arr::get($chunk, 'message'),
        //         $this->senderIdParam => config('config.whatsapp.sender_id'),
        //         $this->templateIdParam => Arr::get($params, 'template_id'),
        //         $this->templateVariableParam => Arr::get($chunk, 'variables'),
        //     ], Arr::get($chunk, 'variables'));
        // }
    }

    public function sendCustomizedWhatsApp(array $recipients, array $params = []): mixed
    {
        return [];

        // foreach (array_chunk($recipients, 20) as $chunk) {
        //     $mobile = Arr::get($chunk, 'mobile');

        //     if (config('config.whatsapp.number_prefix')) {
        //         $mobile = config('config.whatsapp.number_prefix').$mobile;
        //     }

        //     $this->callApi($this->url, [
        //         $this->receiverParam => $mobile,
        //         $this->messageParam => Arr::get($chunk, 'message'),
        //         $this->senderIdParam => config('config.whatsapp.sender_id'),
        //         $this->templateIdParam => Arr::get($params, 'template_id'),
        //         $this->templateVariableParam => Arr::get($chunk, 'variables'),
        //     ], Arr::get($chunk, 'variables'));
        // }
    }

    private function callApi(string $url, array $data = [], array $variables = [])
    {
        $attachments = Arr::get($data, 'attachments', []);

        $components = $this->parseComponents(Arr::get($data, 'template_message'), $variables, $attachments);

        $mobile = Arr::get($data, 'mobile');
        if (config('config.whatsapp.number_prefix')) {
            $mobile = config('config.whatsapp.number_prefix').$mobile;
        }

        $templateName = Arr::get($data, 'template_id');
        if (empty($templateName)) {
            $templateName = Arr::get($data, 'template_code');
        }

        $data = [
            'integrated_number' => config('config.whatsapp.sender_id'),
            'content_type' => 'template',
            'payload' => [
                'messaging_product' => 'whatsapp',
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => config('config.whatsapp.language_code', 'en'),
                        'policy' => 'deterministic',
                    ],
                    'namespace' => config('config.whatsapp.identifier'),
                    'to_and_components' => [
                        [
                            'to' => [$mobile],
                            'components' => [
                                ...$components,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'authkey' => config('config.whatsapp.api_key'),
        ])->post($this->url, $data);

        if (app()->environment('local')) {
            logger($response->status());
            logger($response->body());
        }

        return [
            'status' => $response->status(),
            'message' => Arr::get($response->json(), 'data'),
        ];
    }

    private function parseComponents(string $template, array $values, array $attachments = []): array
    {
        $values = array_change_key_case($values, CASE_UPPER);

        preg_match_all('/##\s*([A-Z0-9_]+)\s*##/i', $template, $matches);

        $variables = $matches[1];

        $components = [];
        foreach ($variables as $i => $varName) {
            if (str_starts_with(strtoupper($varName), 'ATTACHMENT')) {
                $index = (int) filter_var($varName, FILTER_SANITIZE_NUMBER_INT) - 1;
                if (isset($attachments[$index])) {
                    $attachment = $attachments[$index];
                    $components['header_'.($index + 1)] = [
                        'type' => Arr::get($attachment, 'type', 'document'),
                        'filename' => Arr::get($attachment, 'filename', 'Document.pdf'),
                        'value' => Arr::get($attachment, 'url'),
                    ];
                }
                unset($variables[$i]);
            }
        }

        $variables = array_values($variables);

        foreach ($variables as $i => $varName) {
            $index = $i + 1;
            $components["body_{$index}"] = [
                'type' => 'text',
                'value' => $values[strtoupper($varName)] ?? '',
            ];
        }

        return $components;
    }
}
