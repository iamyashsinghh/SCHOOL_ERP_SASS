<?php

namespace App\Jobs\Notifications;

use App\Actions\SendMailTemplate;
use App\Actions\SendPushNotification;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Reminder;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendReminderNotification implements ShouldQueue
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

        $notifyBefore = Arr::get($this->params, 'notify_before', false);

        $templateCode = 'reminder-due-today';

        if ($notifyBefore) {
            $templateCode = 'upcoming-reminder';
        }

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

        $reminder = Reminder::query()
            ->with('users:id,name,email', 'users.pushTokens:id,user_id,type,token')
            ->findOrFail(Arr::get($this->params, 'reminder_id'));

        foreach ($reminder->users as $user) {
            $date = $reminder->date->value;
            $daysLeft = round(Carbon::parse($date)->diffInDays(now(), true));

            $variables = [
                'name' => $user->name,
                'title' => $reminder->title,
                'date' => $reminder->date->formatted,
                'note' => $reminder->note,
                'days_left' => $daysLeft,
            ];

            Notification::create([
                'type' => 'Reminder',
                'data' => $variables,
                'notifiable_id' => $user->id,
                'notifiable_type' => 'User',
                'sender_user_id' => $reminder->user_id,
                'meta' => [
                    'template_code' => $templateCode,
                    'uuid' => (string) $reminder->uuid,
                ],
            ]);

            $pushTokens = $user->pushTokens->pluck('token')->toArray() ?? [];

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

            if ($pushTemplate && $pushTokens && config('config.notification.enable_mobile_push_notification')) {
                (new SendPushNotification)->execute(
                    pushTokens: $pushTokens,
                    template: $pushTemplate,
                    variables: $variables,
                );
            }
        }
    }
}
