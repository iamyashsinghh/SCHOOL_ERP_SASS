<?php

namespace App\Jobs\Notifications\Employee\Leave;

use App\Actions\SendMailTemplate;
use App\Actions\SendPushNotification;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Helpers\SysHelper;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Employee\Leave\Request as LeaveRequest;
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

class SendLeaveRequestRaisedNotifcation implements ShouldQueue
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

        $templateCode = 'employee-leave-request-raised';

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

        $variables = [
            'name' => $employee->name,
            'employee_code' => $employee->code_number,
            'designation' => $employee->designation_name,
            'leave_type' => $leaveRequest->type?->name,
            'start_date' => $leaveRequest->start_date?->formatted,
            'end_date' => $leaveRequest->end_date?->formatted,
            'period' => $leaveRequest->period,
            'reason' => $leaveRequest->reason,
            'url' => url('/app/employee/leave/requests/'.$leaveRequest->uuid),
        ];

        $senderUserId = null;

        SysHelper::setTeam($teamId);
        $users = User::query()
            ->with('pushTokens')
            ->where(function ($q) {
                $q->role('admin');
            })->orWhere(function ($q) {
                $q->permission('leave-request:action');
            })
            ->get();

        $insertData = collect($users->pluck('id'))->map(function ($userId) use ($variables, $leaveRequest, $templateCode, $senderUserId) {
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
                email: $users->first()->email,
                cc: $users->filter(fn ($user) => $user->id !== $users->first()->id)->pluck('email')->all(),
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
            $pushTokens = $users->pluck('pushTokens')->flatten()->pluck('token')->all();

            (new SendPushNotification)->execute(
                pushTokens: $pushTokens,
                template: $pushTemplate,
                variables: $variables,
            );
        }
    }
}
