<?php

namespace App\Jobs\Notifications\Finance;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Actions\SendWhatsApp;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\TempStorage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendFeeInstallmentPaymentLinkNotification implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

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

        $templateCode = 'fee-installment-payment-link';

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

        $paymentUuid = Arr::get($this->params, 'payment_uuid');
        $studentUuid = Arr::get($this->params, 'student_uuid');
        $feeInstallmentUuid = Arr::get($this->params, 'fee_installment_uuid');

        $student = Student::query()
            ->summary($teamId)
            ->where('students.uuid', $studentUuid)
            ->first();

        if (! $student) {
            return;
        }

        $studentFee = Fee::query()
            ->with('installment')
            ->where('student_id', $student->id)
            ->where('uuid', $feeInstallmentUuid)
            ->first();

        if (! $studentFee) {
            return;
        }

        if ($studentFee->paid->value > 0) {
            return;
        }

        $feeInstallmentDate = $studentFee->due_date?->value;

        if (empty($feeInstallmentDate)) {
            $feeInstallmentDate = $studentFee->installment?->due_date?->value;
        }

        $variables = [
            'name' => $student->name,
            'course_name' => $student->course_name,
            'batch_name' => $student->batch_name,
            'course_batch_name' => $student->course_name.' '.$student->batch_name,
            'father_name' => $student->father_name,
            'mother_name' => $student->mother_name,
            'title' => $studentFee->installment?->title,
            'date' => \Cal::date($feeInstallmentDate)->formatted,
            'url' => url('/app/payments/'.$paymentUuid),
            'app_name' => config('config.general.app_name'),
            'team_name' => config('config.team.name'),
        ];

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $student->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $student->contact_number,
                        'message' => $smsTemplate->content,
                        'variables' => $variables,
                    ],
                ],
            ];

            (new SendSMS)->execute($params);
        }

        if ($whatsappTemplate && config('config.notification.enable_whatsapp_notification')) {
            $params = [
                'template_id' => $whatsappTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $student->contact_number,
                        'message' => $whatsappTemplate->content,
                        'variables' => $variables,
                    ],
                ],
            ];

            (new SendWhatsApp)->execute($params);
        }

        if ($pushTemplate && config('config.notification.enable_mobile_push_notification')) {
            // send push
        }

        $payment = TempStorage::query()
            ->where('uuid', $paymentUuid)
            ->first();

        if ($payment) {
            $notificationHistory = $payment->getMeta('notification_history', []);
            $notificationHistory[] = [
                'sent_at' => now()->toDateTimeString(),
            ];
            $payment->setMeta([
                'notification_history' => $notificationHistory,
            ]);
            $payment->save();
        }
    }
}
