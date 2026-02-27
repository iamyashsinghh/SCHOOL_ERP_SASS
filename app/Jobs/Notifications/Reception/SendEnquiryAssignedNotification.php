<?php

namespace App\Jobs\Notifications\Reception;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Enums\Reception\EnquiryNature;
use App\Enums\Reception\EnquiryStatus;
use App\Models\Config\Template;
use App\Models\Notification;
use App\Models\Reception\Enquiry;
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

class SendEnquiryAssignedNotification implements ShouldQueue
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

        $templateCode = 'enquiry-assigned';

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

        $enquiry = Enquiry::query()
            ->with('type', 'source', 'employee.contact')
            ->byTeam($teamId)
            ->findOrFail(Arr::get($this->params, 'enquiry_id'));

        $user = User::query()
            ->where('users.id', $enquiry->employee?->contact?->user_id)
            ->firstOrFail();

        $cc = User::query()
            ->whereIn('users.id', Arr::get($this->params, 'cc', []))
            ->get();

        $variables = [
            'name' => $enquiry->name,
            'code_number' => $enquiry->code_number,
            'nature' => Arr::get(EnquiryNature::getDetail($enquiry->nature), 'label'),
            'status' => Arr::get(EnquiryStatus::getDetail($enquiry->status), 'label'),
            'stage' => $enquiry->stage?->name,
            'type' => $enquiry->type?->name,
            'source' => $enquiry->source?->name,
            'date' => $enquiry->date?->formatted,
            'url' => url('/app/reception/enquiries/'.$enquiry->uuid),
            'assigned_to' => $enquiry->employee?->contact?->name.' - '.$enquiry->employee?->code_number,
        ];

        $senderUserId = null;
        $userIds = [$user?->id];

        $userIds = array_merge($userIds, $cc->pluck('id')->toArray());

        $insertData = collect($userIds)->map(function ($userId) use ($variables, $enquiry, $templateCode, $senderUserId) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'Enquiry',
                'data' => json_encode($variables),
                'notifiable_id' => $userId,
                'notifiable_type' => 'User',
                'sender_user_id' => $senderUserId,
                'meta' => json_encode([
                    'template_code' => $templateCode,
                    'uuid' => (string) $enquiry->uuid,
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
