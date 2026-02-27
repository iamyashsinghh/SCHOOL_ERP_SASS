<?php

namespace App\Services\Config\WhatsAppProvider;

use App\Contracts\WhatsAppService;
use App\Contracts\WhatsAppTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class IsmsMy extends WhatsAppTemplate implements WhatsAppService
{
    private string $url;

    private string $appId;

    private string $appSecret;

    private string $username;

    private string $password;

    private string $senderId;

    public function __construct()
    {
        $this->appId = config('config.whatsapp.api_id');
        $this->appSecret = config('config.whatsapp.api_secret');
        $this->username = config('config.whatsapp.username');
        $this->password = config('config.whatsapp.password');
        $this->senderId = config('config.whatsapp.sender_id');
        $this->url = 'https://ww3.isms.com.my/isms_send_waba.php';
    }

    public function sendWhatsApp(array $recipient, array $params = []): mixed
    {
        $templateId = $this->getTemplateId($params);

        if (! $templateId) {
            return [];
        }

        $variables = Arr::get($params, 'variables', []);

        $mobile = Arr::get($recipient, 'mobile');

        if (config('config.whatsapp.number_prefix')) {
            $mobile = config('config.whatsapp.number_prefix').$mobile;
        }

        $filteredVariables = $this->filterVariables($variables, Arr::get($recipient, 'message'));

        $filteredVariables = array_change_key_case($filteredVariables, CASE_UPPER);

        return $this->callApi($this->url, [
            'mobile' => $mobile,
            'template_id' => $templateId,
        ], $filteredVariables);
    }

    public function sendBulkWhatsApp(array $recipients, array $params = []): mixed
    {
        return [];
        // $templateId = $this->getTemplateId($params);
        // foreach (array_chunk($recipients, 20) as $chunk) {
        //     $mobile = Arr::get($chunk, 'mobile');

        //     if (config('config.whatsapp.number_prefix')) {
        //         $mobile = config('config.whatsapp.number_prefix').$mobile;
        //     }
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
        $data = [
            'AppId' => $this->appId,
            'AppSecret' => $this->appSecret,
            'un' => $this->username,
            'pwd' => $this->password,
            'agreedterm' => 'YES',
            'Type' => 'template',
            'TemplateCode' => Arr::get($data, 'template_id'),
            'TemplateParams' => $variables,
            'Language' => 'en',
            'From' => $this->senderId,
            'To' => Arr::get($data, 'mobile'),
        ];

        // logger($data);
        // throw ValidationException::withMessages(['message' => 'test']);

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->withOptions([
            'verify' => config('app.env') == 'local' ? false : true,
        ])
            ->post($url, $data);

        // if ($response->status() != 200) {
        //     throw ValidationException::withMessages(['message' => trans('communication.failed')]);
        // }

        return [
            'status' => $response->status(),
            'message' => Arr::get($response->json(), 'data'),
        ];
    }

    private function filterVariables(array $variables, ?string $message = null): array
    {
        preg_match_all('/##\s*([A-Z0-9_]+)\s*##/i', $message, $matches);
        $availableVariables = $matches[1];

        return collect($variables)->filter(function ($value, $key) use ($availableVariables) {
            return in_array(strtoupper($key), $availableVariables);
        })->toArray();
    }
}
