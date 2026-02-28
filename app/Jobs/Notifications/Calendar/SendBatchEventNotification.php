<?php

namespace App\Jobs\Notifications\Calendar;

use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Calendar\Event;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Notification;
use App\Support\HasAudience;
use App\Support\MergeGuardianContact;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

class SendBatchEventNotification implements ShouldQueue
{
    use Dispatchable, HasAudience, InteractsWithQueue, MergeGuardianContact, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');
        $senderUserId = Arr::get($this->params, 'sender_user_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'notification', 'mail', 'sms', 'whatsapp', 'whatsapp', 'push']);

        if (! config('config.notification.enable_notification')) {
            return;
        }

        $templateCode = 'event-published';

        $templates = Template::query()
            ->whereCode($templateCode)
            ->whereNotNull('enabled_at')
            ->get();

        if (! $templates->count()) {
            return;
        }

        $event = Event::query()
            ->with('type')
            ->findOrFail(Arr::get($this->params, 'event_id'));

        $variables = [
            'type' => $event->type?->name,
            'title' => $event->title,
            'venue' => $event->venue,
            'duration' => $event->duration_in_detail,
        ];

        $studentAudienceType = $event->is_public ? 'all' : Arr::get($event->audience, 'student_type', 'all');
        $employeeAudienceType = $event->is_public ? 'all' : Arr::get($event->audience, 'employee_type', 'all');
        $studentAudiences = $event->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Division', 'Course', 'Batch']);
        })->pluck('audienceable_id');
        $employeeAudiences = $event->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Department', 'Designation']);
        })->pluck('audienceable_id');

        $contacts = $this->getContacts([
            'student_audience_type' => $studentAudienceType,
            'employee_audience_type' => $employeeAudienceType,
            'student_audiences' => $studentAudiences,
            'employee_audiences' => $employeeAudiences,
            'period_id' => $event->period_id,
            'team_id' => $teamId,
        ]);

        $contactQuery = $this->mergeGuardianContact($contacts->pluck('id')->toArray(), 'query');

        $jobs = [];

        $contactQuery
            ->with('user:id', 'user.pushTokens:id,user_id,type,token')
            ->chunk(100, function ($chunk) use (&$jobs, $templates, $templateCode, $variables, $teamId, $event, $senderUserId) {
                $userIds = $chunk->filter(function ($contact) {
                    return ! is_null($contact->user_id);
                })->pluck('user_id')->unique()->toArray();

                $insertData = collect($userIds)->map(function ($userId) use ($variables, $event, $templateCode, $senderUserId) {
                    return [
                        'uuid' => (string) Str::uuid(),
                        'type' => 'Event',
                        'data' => json_encode($variables),
                        'notifiable_id' => $userId,
                        'notifiable_type' => 'User',
                        'sender_user_id' => $senderUserId,
                        'meta' => json_encode([
                            'template_code' => $templateCode,
                            'uuid' => (string) $event->uuid,
                        ]),
                        'created_at' => now()->toDateTimeString(),
                    ];
                });

                Notification::insert($insertData->toArray());

                foreach ($chunk as $contact) {
                    $pushTokens = $contact->user?->pushTokens->pluck('token')->toArray() ?? [];

                    $jobs[] = new SendEventNotification([
                        'templates' => $templates,
                        'email' => $contact->email,
                        'contact_number' => $contact->contact_number,
                        'push_tokens' => $pushTokens,
                        'variables' => $variables,
                        'team_id' => $teamId,
                    ]);
                }
            });

        Bus::batch($jobs)
            ->then(function (Batch $batch) {})
            ->catch(function (Batch $batch, Throwable $e) {})
            ->finally(function (Batch $batch) {})
            ->dispatch();
    }
}
