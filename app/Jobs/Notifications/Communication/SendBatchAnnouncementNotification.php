<?php

namespace App\Jobs\Notifications\Communication;

use App\Concerns\SetConfigForJob;
use App\Models\Communication\Announcement;
use App\Models\Config\Template;
use App\Models\Notification;
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

class SendBatchAnnouncementNotification implements ShouldQueue
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

        $templateCode = 'announcement-published';

        $templates = Template::query()
            ->whereCode($templateCode)
            ->whereNotNull('enabled_at')
            ->get();

        if (! $templates->count()) {
            return;
        }

        $announcement = Announcement::query()
            ->with('type')
            ->findOrFail(Arr::get($this->params, 'announcement_id'));

        $variables = [
            'type' => $announcement->type?->name,
            'title' => $announcement->title,
        ];

        $studentAudienceType = $announcement->is_public ? 'all' : Arr::get($announcement->audience, 'student_type', 'all');
        $employeeAudienceType = $announcement->is_public ? 'all' : Arr::get($announcement->audience, 'employee_type', 'all');
        $studentAudiences = $announcement->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Division', 'Course', 'Batch']);
        })->pluck('audienceable_id');
        $employeeAudiences = $announcement->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Department', 'Designation']);
        })->pluck('audienceable_id');

        $contacts = $this->getContacts([
            'student_audience_type' => $studentAudienceType,
            'employee_audience_type' => $employeeAudienceType,
            'student_audiences' => $studentAudiences,
            'employee_audiences' => $employeeAudiences,
            'period_id' => $announcement->period_id,
            'team_id' => $teamId,
        ]);

        $contactQuery = $this->mergeGuardianContact($contacts->pluck('id')->toArray(), 'query');

        $jobs = [];

        $contactQuery
            ->with('user:id', 'user.pushTokens:id,user_id,type,token')
            ->chunk(100, function ($chunk) use (&$jobs, $templates, $templateCode, $variables, $teamId, $announcement, $senderUserId) {
                $userIds = $chunk->filter(function ($contact) {
                    return ! is_null($contact->user_id);
                })->pluck('user_id')->unique()->toArray();

                $insertData = collect($userIds)->map(function ($userId) use ($variables, $announcement, $templateCode, $senderUserId) {
                    return [
                        'uuid' => (string) Str::uuid(),
                        'type' => 'Announcement',
                        'data' => json_encode($variables),
                        'notifiable_id' => $userId,
                        'notifiable_type' => 'User',
                        'sender_user_id' => $senderUserId,
                        'meta' => json_encode([
                            'template_code' => $templateCode,
                            'uuid' => (string) $announcement->uuid,
                        ]),
                        'created_at' => now()->toDateTimeString(),
                    ];
                });

                Notification::insert($insertData->toArray());

                foreach ($chunk as $contact) {
                    $pushTokens = $contact->user?->pushTokens->pluck('token')->toArray() ?? [];

                    $jobs[] = new SendAnnouncementNotification([
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
