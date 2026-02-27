<?php

namespace App\Jobs\Notifications\Resource;

use App\Actions\SendMailTemplate;
use App\Actions\SendPushNotification;
use App\Concerns\SetConfigForJob;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendDiaryNotification implements ShouldQueue
{
    use Batchable, Dispatchable, HasAudience, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'notification', 'mail', 'sms', 'whatsapp']);

        $variables = Arr::get($this->params, 'variables');
        $pushTokens = Arr::get($this->params, 'push_tokens');
        $email = Arr::get($this->params, 'email');
        $contactNumber = Arr::get($this->params, 'contact_number');

        $templates = collect(Arr::get($this->params, 'templates'));

        $mailTemplate = $templates->where('type', 'mail')->first();
        $smsTemplate = $templates->where('type', 'sms')->first();
        $whatsappTemplate = $templates->where('type', 'whatsapp')->first();
        $pushTemplate = $templates->where('type', 'push')->first();

        if ($mailTemplate && $email && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && $contactNumber && config('config.notification.enable_sms_notification')) {
            // send sms
        }

        if ($whatsappTemplate && $contactNumber && config('config.notification.enable_whatsapp_notification')) {
            // send whatsapp
        }

        if ($pushTemplate && $pushTokens && config('config.notification.enable_mobile_push_notification')) {
            (new SendPushNotification)->execute(
                pushTokens: $pushTokens,
                template: $pushTemplate,
                variables: $variables,
            );
        }
    }
}
