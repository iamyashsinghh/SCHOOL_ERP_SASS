<?php

namespace App\Jobs\Notifications;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Config\Template;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendOTPNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $this->setConfig(modules: ['general', 'assets', 'system', 'social_network', 'notification', 'mail', 'sms', 'whatsapp']);

        $templateCode = 'otp';

        $type = Arr::get($this->params, 'type', ['mail', 'sms', 'whatsapp', 'push']);

        $templates = Template::query()
            ->whereCode($templateCode)
            ->whereNotNull('enabled_at')
            ->whereIn('type', $type)
            ->get();

        if (! $templates->count()) {
            return;
        }

        $mailTemplate = $templates->where('type', 'mail')->first();
        $smsTemplate = $templates->where('type', 'sms')->first();
        $whatsappTemplate = $templates->where('type', 'whatsapp')->first();
        $pushTemplate = $templates->where('type', 'push')->first();

        $userId = Arr::get($this->params, 'user_id');

        $user = $userId ? User::query()->findOrFail($userId) : null;

        $variables = [
            'name' => $user?->name ?? Arr::get($this->params, 'name'),
            'code' => Arr::get($this->params, 'code'),
            'token_lifetime' => Arr::get($this->params, 'token_lifetime', 30),
        ];

        if ($mailTemplate) {
            (new SendMailTemplate)->execute(
                email: $user?->email ?? Arr::get($this->params, 'email'),
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => Arr::get($this->params, 'contact_number'),
                        'message' => $smsTemplate->content,
                        'variables' => $variables,
                    ],
                ],
            ];

            (new SendSMS)->execute($params);
        }

        if ($whatsappTemplate && config('config.notification.enable_whatsapp_notification')) {
            // send whatsapp
        }

        if ($pushTemplate && config('config.notification.enable_mobile_push_notification')) {
            // send push
        }
    }
}
