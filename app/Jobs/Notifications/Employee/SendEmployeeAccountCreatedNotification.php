<?php

namespace App\Jobs\Notifications\Employee;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Employee\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendEmployeeAccountCreatedNotification implements ShouldQueue
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

        $templateCode = 'employee-account-created';

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

        $employee = Employee::query()
            ->with('contact')
            ->findOrFail(Arr::get($this->params, 'employee_id'));

        $variables = [
            ...$this->params,
            'url' => url('/app'),
        ];

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $employee->contact?->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $employee->contact?->contact_number,
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
