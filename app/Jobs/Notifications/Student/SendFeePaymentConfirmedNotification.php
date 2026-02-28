<?php

namespace App\Jobs\Notifications\Student;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Actions\SendWhatsApp;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Student\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendFeePaymentConfirmedNotification implements ShouldQueue
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

        $templateCode = 'fee-payment-confirmed';

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

        $student = Student::query()
            ->with('fees')
            ->summary($teamId)
            ->findOrFail(Arr::get($this->params, 'student_id'));

        $transaction = Transaction::query()
            ->with('payments.method')
            ->where('id', '=', Arr::get($this->params, 'transaction_id'))
            ->firstOrFail();

        $feeTitle = $transaction->getMeta('fee_group_name');
        if (empty($feeTitle)) {
            $feeTitle = $transaction->getMeta('fee_title', 'Fee');
        }

        $paymentMethodName = $transaction->payments->first()?->method?->name ?? 'N/A';

        $feeSummary = $student->getFeeSummary();
        $balance = Arr::get($feeSummary, 'balance_fee')?->formatted;

        $variables = [
            'name' => $student->name,
            'course_name' => $student->course_name,
            'batch_name' => $student->batch_name,
            'course_batch_name' => $student->course_name.' '.$student->batch_name,
            'fee_title' => $feeTitle,
            'voucher_number' => $transaction->code_number,
            'reference_number' => Arr::get($transaction->payment_gateway, 'reference_number'),
            'amount' => $transaction->amount->formatted,
            'fee_balance' => $balance,
            'datetime' => $transaction->is_online ? $transaction->processed_at->formatted : \Cal::dateTime($transaction->created_at)->formatted,
            'payment_method' => $paymentMethodName,
            'url' => url('/app/students/'.$student->uuid.'/fee'),
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
                        'attachments' => [
                            [
                                'filename' => $transaction->code_number.'.pdf',
                                'type' => 'document',
                                'url' => route('payment.export', [
                                    'code_number' => $transaction->code_number,
                                    'uuid' => $student->uuid,
                                    'output' => 'pdf',
                                    'reference_number' => $transaction->uuid,
                                ], true),
                            ],
                        ],
                    ],
                ],
            ];

            (new SendWhatsApp)->execute($params);
        }

        if ($pushTemplate && config('config.notification.enable_mobile_push_notification')) {
            // send push
        }
    }
}
