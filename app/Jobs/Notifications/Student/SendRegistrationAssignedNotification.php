<?php

namespace App\Jobs\Notifications\Student;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Enums\Student\RegistrationStatus;
use App\Models\Config\Template;
use App\Models\Notification;
use App\Models\Student\Registration;
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

class SendRegistrationAssignedNotification implements ShouldQueue
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

        $templateCode = 'registration-assigned';

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

        $registration = Registration::query()
            ->with('course', 'contact', 'employee.contact')
            ->byTeam($teamId)
            ->findOrFail(Arr::get($this->params, 'registration_id'));

        $user = User::query()
            ->where('users.id', $registration->employee?->contact?->user_id)
            ->firstOrFail();

        $cc = User::query()
            ->whereIn('users.id', Arr::get($this->params, 'cc', []))
            ->get();

        $variables = [
            'name' => $registration->contact?->name,
            'code_number' => $registration->code_number,
            'status' => Arr::get(RegistrationStatus::getDetail($registration->status), 'label'),
            'stage' => $registration->stage?->name,
            'course' => $registration->course?->name,
            'date' => $registration->date?->formatted,
            'url' => url('/app/student/registrations/'.$registration->uuid),
            'assigned_to' => $registration->employee?->contact?->name.' - '.$registration->employee?->code_number,
        ];

        $senderUserId = null;
        $userIds = [$user?->id];

        $userIds = array_merge($userIds, $cc->pluck('id')->toArray());

        $insertData = collect($userIds)->map(function ($userId) use ($variables, $registration, $templateCode, $senderUserId) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'Registration',
                'data' => json_encode($variables),
                'notifiable_id' => $userId,
                'notifiable_type' => 'User',
                'sender_user_id' => $senderUserId,
                'meta' => json_encode([
                    'template_code' => $templateCode,
                    'uuid' => (string) $registration->uuid,
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
                cc: $cc->pluck('email')->toArray(),
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
