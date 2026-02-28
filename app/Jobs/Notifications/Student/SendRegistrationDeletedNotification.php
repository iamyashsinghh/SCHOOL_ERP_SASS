<?php

namespace App\Jobs\Notifications\Student;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Notification;
use App\Models\Tenant\User;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendRegistrationDeletedNotification implements ShouldQueue
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

        if (! config('config.notification.enable_notification')) {
            return;
        }

        $templateCode = 'registration-deleted';

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
            ->where('users.id', Arr::get($this->params, 'user_id'))
            ->firstOrFail();

        $codeNumber = Arr::get($this->params, 'code_number');

        $variables = [
            'code_number' => $codeNumber,
        ];

        $senderUserId = null;
        $userIds = [$user?->id];

        $insertData = collect($userIds)->map(function ($userId) use ($variables, $templateCode, $senderUserId) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'Registration',
                'data' => json_encode($variables),
                'notifiable_id' => $userId,
                'notifiable_type' => 'User',
                'sender_user_id' => $senderUserId,
                'meta' => json_encode([
                    'template_code' => $templateCode,
                ]),
                'created_at' => now()->toDateTimeString(),
            ];
        });

        if ($insertData->isEmpty()) {
            return;
        }

        Notification::insert($insertData->toArray());

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $user?->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            // $params = [
            //     'template_id' => $smsTemplate->getMeta('template_id'),
            //     'recipients' => [
            //         [
            //             'mobile' => $employee->contact_number,
            //             'message' => $smsTemplate->content,
            //             'variables' => $variables,
            //         ],
            //     ],
            // ];

            // (new SendSMS)->execute($params);
        }

        if ($whatsappTemplate && config('config.notification.enable_whatsapp_notification')) {
            // send whatsapp
        }

        if ($pushTemplate && config('config.notification.enable_mobile_push_notification')) {
            // send push
        }
    }
}
