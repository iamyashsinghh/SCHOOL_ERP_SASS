<?php

namespace App\Jobs\Notifications\Recruitment;

use App\Actions\SendMailTemplate;
use App\Actions\SendPushNotification;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Config\Template;
use App\Models\Notification;
use App\Models\Recruitment\Application;
use App\Models\User;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendJobApplicationReceivedNotification implements ShouldQueue
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

        $templateCode = 'job-application-received';

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

        $jobApplication = Application::query()
            ->with('contact', 'vacancy', 'designation')
            ->findOrFail(Arr::get($this->params, 'application_id'));

        $user = User::query()
            ->where('meta->is_default', true)
            ->firstOrFail();

        $variables = [
            'name' => $jobApplication->contact->name,
            'vacancy_code' => $jobApplication->vacancy->code_number,
            'vacancy_title' => $jobApplication->vacancy->title,
            'designation' => $jobApplication->designation->name,
            'availability_date' => $jobApplication->availability_date?->formatted,
            'url' => url('/app/job/applications/'.$jobApplication->uuid),
        ];

        $senderUserId = null;
        $userIds = [$user->id];

        $insertData = collect($userIds)->map(function ($userId) use ($variables, $jobApplication, $templateCode, $senderUserId) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'JobApplication',
                'data' => json_encode($variables),
                'notifiable_id' => $userId,
                'notifiable_type' => 'User',
                'sender_user_id' => $senderUserId,
                'meta' => json_encode([
                    'template_code' => $templateCode,
                    'uuid' => (string) $jobApplication->uuid,
                ]),
                'created_at' => now()->toDateTimeString(),
            ];
        });

        Notification::insert($insertData->toArray());

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $user->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $jobApplication->contact?->contact_number,
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
            (new SendPushNotification)->execute(
                pushTokens: [],
                template: $pushTemplate,
                variables: $variables,
            );
        }
    }
}
