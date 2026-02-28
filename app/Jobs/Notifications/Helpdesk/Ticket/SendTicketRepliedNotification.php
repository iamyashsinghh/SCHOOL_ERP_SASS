<?php

namespace App\Jobs\Notifications\Helpdesk\Ticket;

use App\Actions\SendMailTemplate;
use App\Actions\SendSMS;
use App\Concerns\SetConfigForJob;
use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Helpdesk\Ticket\Message as TicketMessage;
use App\Models\Tenant\Helpdesk\Ticket\Ticket;
use App\Models\Tenant\Notification;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendTicketRepliedNotification implements ShouldQueue
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

        $templateCode = 'ticket-replied';

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

        $ticket = Ticket::query()
            ->with('category', 'priority', 'assignees')
            ->byTeam($teamId)
            ->findOrFail(Arr::get($this->params, 'ticket_id'));

        $message = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->findOrFail(Arr::get($this->params, 'message_id'));

        $requester = $ticket->user_id;
        $reviewers = $ticket->assignees->pluck('user_id')->toArray();

        $userIds = array_merge([$requester], $reviewers);

        $employees = Employee::query()
            ->summaryForGuest()
            ->whereIn('contacts.user_id', $userIds)
            ->get();

        $senderEmployee = $employees->where('user_id', $message->user_id)->first();

        $receiverEmployees = $employees->filter(function ($employee) use ($senderEmployee) {
            return $employee->id !== $senderEmployee?->id;
        });

        foreach ($receiverEmployees as $employee) {
            $variables = [
                'name' => $employee->name,
                'employee_name' => $senderEmployee?->name ?? 'Admin',
                'employee_designation' => $senderEmployee?->designation_name ?? 'System Admin',
                'number' => $ticket->code_number,
                'title' => $ticket->title,
                'category' => $ticket->category->name,
                'priority' => $ticket->priority->name,
                'status' => Arr::get(TicketStatus::getDetail($ticket->status), 'label'),
                'url' => url('/app/helpdesk/tickets/'.$ticket->uuid),
            ];

            $senderUserId = $employee->user_id;
            $userIds = [$employee->user_id];

            $insertData = collect($userIds)->map(function ($userId) use ($variables, $ticket, $templateCode, $senderUserId) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'type' => 'Ticket',
                    'data' => json_encode($variables),
                    'notifiable_id' => $userId,
                    'notifiable_type' => 'User',
                    'sender_user_id' => $senderUserId,
                    'meta' => json_encode([
                        'template_code' => $templateCode,
                        'uuid' => (string) $ticket->uuid,
                    ]),
                    'created_at' => now()->toDateTimeString(),
                ];
            });

            Notification::insert($insertData->toArray());

            if ($mailTemplate && config('config.notification.enable_mail_notification')) {
                (new SendMailTemplate)->execute(
                    email: $employee?->email,
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
}
