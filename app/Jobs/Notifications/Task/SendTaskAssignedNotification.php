<?php

namespace App\Jobs\Notifications\Task;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Task\Task;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendTaskAssignedNotification implements ShouldQueue
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

        $templateCode = 'task-assigned';

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

        $task = Task::query()
            ->with('category', 'priority', 'members')
            ->findOrFail(Arr::get($this->params, 'task_id'));

        $employee = Employee::query()
            ->summaryForGuest()
            ->where('employees.id', Arr::get($this->params, 'employee_id'))
            ->firstOrFail();

        $variables = [
            'name' => $employee->name,
            'number' => $task->code_number,
            'title' => $task->title,
            'category' => $task->category->name,
            'priority' => $task->priority->name,
            'start_date' => $task->start_date->formatted,
            'due_date' => $task->due_date->formatted,
            'url' => url('/app/tasks/'.$task->uuid),
        ];

        $senderUserId = null;
        $userIds = $employee?->user_id ? [$employee->user_id] : [];

        $insertData = collect($userIds)->map(function ($userId) use ($variables, $task, $templateCode, $senderUserId) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'Task',
                'data' => json_encode($variables),
                'notifiable_id' => $userId,
                'notifiable_type' => 'User',
                'sender_user_id' => $senderUserId,
                'meta' => json_encode([
                    'template_code' => $templateCode,
                    'uuid' => (string) $task->uuid,
                ]),
                'created_at' => now()->toDateTimeString(),
            ];
        });

        Notification::insert($insertData->toArray());

        if ($mailTemplate && config('config.notification.enable_mail_notification')) {
            (new SendMailTemplate)->execute(
                email: $employee->email,
                variables: $variables,
                template: $mailTemplate,
            );
        }

        if ($smsTemplate && config('config.notification.enable_sms_notification')) {
            $params = [
                'template_id' => $smsTemplate->getMeta('template_id'),
                'recipients' => [
                    [
                        'mobile' => $employee->contact_number,
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
