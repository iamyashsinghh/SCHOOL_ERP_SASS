<?php

namespace App\Jobs\Notifications\Student;

use App\Actions\SendMailTemplate;
use App\Actions\SendPushNotification;
use App\Actions\SendSMS;
use App\Actions\SendWhatsApp;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Notification;
use App\Support\MergeGuardianContact;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendAttendanceNotification implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, MergeGuardianContact, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'notification', 'mail', 'sms', 'whatsapp']);

        $templateCode = 'student-daily-attendance';

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

        $senderUserId = Arr::get($this->params, 'sender_user_id');
        $contactId = Arr::get($this->params, 'contact_id');

        $contacts = $this->mergeGuardianContact([$contactId], 'query')
            ->with('user:id', 'user.pushTokens:id,user_id,type,token')
            ->get();

        $userIds = [];
        $pushTokens = [];

        foreach ($contacts as $contact) {
            if ($contact->user_id) {
                $userIds[] = $contact->user_id;
                $pushTokens = array_merge($pushTokens, $contact->user?->pushTokens->pluck('token')->toArray() ?? []);
            }
        }

        $studentContact = $contacts->firstWhere('id', $contactId);

        $variables = Arr::get($this->params, 'variables');

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $studentContact?->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $studentContact?->contact_number,
                        'message' => $smsTemplate->content,
                        'variables' => $variables,
                    ],
                ],
            ];

            (new SendSMS)->execute($params);
        }

        if ($whatsappTemplate && config('config.notification.enable_whatsapp_notification')) {
            $data = [
                'template_id' => $whatsappTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $studentContact?->contact_number,
                        'message' => $whatsappTemplate->content,
                        'variables' => $variables,
                    ],
                ],
            ];

            (new SendWhatsApp)->execute($data);
        }

        if ($pushTemplate) {
            $insertData = collect($userIds)->map(function ($userId) use ($variables, $templateCode, $senderUserId) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'type' => 'StudentAttendance',
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

            Notification::insert($insertData->toArray());

            (new SendPushNotification)->execute(
                pushTokens: $pushTokens,
                template: $pushTemplate,
                variables: $variables,
            );
        }
    }
}
