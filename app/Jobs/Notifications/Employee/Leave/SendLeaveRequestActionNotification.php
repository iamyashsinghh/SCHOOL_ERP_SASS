<?php

namespace App\Jobs\Notifications\Employee\Leave;

use App\Actions\SendMailTemplate;
use App\Actions\SendPushNotification;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Config\Template;
use App\Models\Employee\Employee;
use App\Models\Employee\Leave\Request as LeaveRequest;
use App\Models\Notification;
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

class SendLeaveRequestActionNotification implements ShouldQueue
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

        $templateCode = 'employee-leave-request-action';

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

        $leaveRequest = LeaveRequest::query()
            ->where('model_type', 'Employee')
            ->findOrFail(Arr::get($this->params, 'leave_request_id'));

        $employee = Employee::query()
            ->summaryForGuest()
            ->findOrFail($leaveRequest->model_id);

        $user = User::query()
            ->where('meta->is_default', true)
            ->firstOrFail();

        $variables = [
            'name' => $employee->name,
            'employee_code' => $employee->code_number,
            'designation' => $employee->designation_name,
            'leave_type' => $leaveRequest->type?->name,
            'start_date' => $leaveRequest->start_date?->formatted,
            'end_date' => $leaveRequest->end_date?->formatted,
            'period' => $leaveRequest->period,
            'reason' => $leaveRequest->reason,
            'status' => trans('employee.leave.request.statuses.'.$leaveRequest->status?->value),
            'url' => url('/app/employee/leave/requests/'.$leaveRequest->uuid),
        ];

        $senderUserId = null;

        $user = User::query()
            ->with('pushTokens')
            ->where('id', $employee->user_id)
            ->firstOrFail();

        $insertData = collect([$user->id])->map(function ($userId) use ($variables, $leaveRequest, $templateCode, $senderUserId) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'EmployeeLeaveRequest',
                'data' => json_encode($variables),
                'notifiable_id' => $userId,
                'notifiable_type' => 'User',
                'sender_user_id' => $senderUserId,
                'meta' => json_encode([
                    'template_code' => $templateCode,
                    'uuid' => (string) $leaveRequest->uuid,
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
            $pushTokens = $user->pushTokens->pluck('token')->all();

            (new SendPushNotification)->execute(
                pushTokens: $pushTokens,
                template: $pushTemplate,
                variables: $variables,
            );
        }
    }
}
