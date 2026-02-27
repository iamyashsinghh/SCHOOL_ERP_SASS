<?php

namespace App\Jobs\Notifications\Student;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Config\Template;
use App\Models\Guardian;
use App\Models\Student\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendRegistrationRejectedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

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

        $isOnline = (bool) Arr::get($this->params, 'is_online');

        $templateCode = 'registration-rejected';

        // if ($isOnline) {
        //     $templateCode = 'online-registration-rejected';
        // }

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
            ->with('contact', 'course.division.program', 'period.session')
            ->findOrFail(Arr::get($this->params, 'registration_id'));

        $contact = $registration->contact;

        $guardians = Guardian::query()
            ->select('guardians.*', 'contacts.email')
            ->join('contacts', 'guardians.contact_id', '=', 'contacts.id')
            ->whereNotNull('contacts.email')
            ->wherePrimaryContactId($contact->id)
            ->get();

        $variables = [
            'name' => $registration->contact->name,
            'application_number' => $registration->getMeta('application_number'),
            'registration_number' => $registration->code_number,
            'program' => $registration->course?->division?->program?->name,
            'session' => $registration->period?->session?->name,
            'period' => $registration->period->name,
            'course' => $registration->course->name,
            'reason' => $registration->rejection_remarks,
        ];

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $registration->contact?->email,
                variables: $variables,
                template: $mailTemplate,
                cc: $guardians->pluck('email')->toArray(),
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $registration->contact?->contact_number,
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
