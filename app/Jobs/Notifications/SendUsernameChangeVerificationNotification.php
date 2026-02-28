<?php

namespace App\Jobs\Notifications;

use App\Actions\SendMailTemplate;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendUsernameChangeVerificationNotification implements ShouldQueue
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

        $templateCode = 'username-change-verification';

        $templates = Template::query()
            ->whereCode($templateCode)
            ->whereNotNull('enabled_at')
            ->get();

        if (! $templates->count()) {
            return;
        }

        $mailTemplate = $templates->where('type', 'mail')->first();
        $smsTemplate = $templates->where('type', 'sms')->first();
        $whatsappTemplate = $templates->where('type', 'whatsapp')->first();
        $pushTemplate = $templates->where('type', 'push')->first();

        $user = User::query()
            ->findOrFail(Arr::get($this->params, 'user_id'));

        $variables = [
            'name' => $user->name,
            'code' => Arr::get($this->params, 'code'),
            'token_lifetime' => Arr::get($this->params, 'token_lifetime', 30),
            'old_username' => Arr::get($this->params, 'old_username'),
            'new_username' => Arr::get($this->params, 'new_username'),
        ];

        if ($mailTemplate) {
            (new SendMailTemplate)->execute(
                email: $user->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            // send sms
        }

        if ($whatsappTemplate && config('config.notification.enable_whatsapp_notification')) {
            // send whatsapp
        }

        if ($pushTemplate && config('config.notification.enable_mobile_push_notification')) {
            // send push
        }
    }
}
