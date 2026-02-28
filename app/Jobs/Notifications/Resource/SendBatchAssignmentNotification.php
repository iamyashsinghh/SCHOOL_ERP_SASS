<?php

namespace App\Jobs\Notifications\Resource;

use App\Concerns\SetConfigForJob;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Resource\Assignment;
use App\Models\Tenant\Student\Student;
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

class SendBatchAssignmentNotification implements ShouldQueue
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

        $templateCode = 'assignment-published';

        $templates = Template::query()
            ->whereCode($templateCode)
            ->whereNotNull('enabled_at')
            ->get();

        if (! $templates->count()) {
            return;
        }

        $assignment = Assignment::query()
            ->with('type', 'records')
            ->findOrFail(Arr::get($this->params, 'assignment_id'));

        $variables = [
            'type' => $assignment->type?->name,
            'title' => $assignment->title,
            'date' => $assignment->date->formatted,
            'due_date' => $assignment->due_date->formatted,
        ];

        $batchIds = [];

        foreach ($assignment->records as $record) {
            $batchIds[] = $record->batch_id;
        }

        $students = Student::query()
            ->select('students.id', 'students.contact_id')
            ->byPeriod($assignment->period_id)
            ->join('contacts', 'students.contact_id', '=', 'contacts.id')
            ->whereIn('students.batch_id', $batchIds)
            ->get();

        $contactQuery = $this->mergeGuardianContact($students->pluck('contact_id')->toArray(), 'query');

        $jobs = [];

        $contactQuery
            ->with('user:id', 'user.pushTokens:id,user_id,type,token')
            ->chunk(100, function ($chunk) use (&$jobs, $templates, $templateCode, $variables, $teamId, $assignment, $senderUserId) {
                $userIds = $chunk->filter(function ($contact) {
                    return ! is_null($contact->user_id);
                })->pluck('user_id')->unique()->toArray();

                $insertData = collect($userIds)->map(function ($userId) use ($variables, $assignment, $templateCode, $senderUserId) {
                    return [
                        'uuid' => (string) Str::uuid(),
                        'type' => 'Assignment',
                        'data' => json_encode($variables),
                        'notifiable_id' => $userId,
                        'notifiable_type' => 'User',
                        'sender_user_id' => $senderUserId,
                        'meta' => json_encode([
                            'template_code' => $templateCode,
                            'uuid' => (string) $assignment->uuid,
                        ]),
                        'created_at' => now()->toDateTimeString(),
                    ];
                });

                Notification::insert($insertData->toArray());

                foreach ($chunk as $contact) {
                    $pushTokens = $contact->user?->pushTokens->pluck('token')->toArray() ?? [];

                    $jobs[] = new SendAssignmentNotification([
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
