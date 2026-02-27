<?php

namespace App\Jobs\Notifications\Communication;

use App\Concerns\SetConfigForJob;
use App\Models\Communication\Communication;
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

class SendBatchPushNotification implements ShouldQueue
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

        $this->setConfig($teamId, ['general', 'assets', 'system']);

        $communication = Communication::query()
            ->with('audiences')
            ->findOrFail(Arr::get($this->params, 'communication_id'));

        $variables = [];

        $studentAudienceType = Arr::get($communication->audience, 'student_type', 'all');
        $employeeAudienceType = Arr::get($communication->audience, 'employee_type', 'all');
        $studentAudiences = $communication->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Division', 'Course', 'Batch']);
        })->pluck('audienceable_id');
        $employeeAudiences = $communication->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Department', 'Designation']);
        })->pluck('audienceable_id');

        $contacts = $this->getContacts([
            'student_audience_type' => $studentAudienceType,
            'employee_audience_type' => $employeeAudienceType,
            'student_audiences' => $studentAudiences,
            'employee_audiences' => $employeeAudiences,
            'period_id' => $communication->period_id,
            'team_id' => $teamId,
        ]);

        $contactQuery = $this->mergeGuardianContact($contacts->pluck('id')->toArray(), 'query');

        $pushTemplate = new Template;
        $pushTemplate->type = 'push';
        $pushTemplate->code = 'dynamic-push-notification';
        $pushTemplate->subject = $communication->subject;
        $pushTemplate->content = $communication->content;

        $jobs = [];

        $contactQuery
            ->with('user:id', 'user.pushTokens:id,user_id,type,token')
            ->chunk(100, function ($chunk) use (&$jobs, $communication, $variables, $teamId, $pushTemplate) {
                $userIds = $chunk->filter(function ($contact) {
                    return ! is_null($contact->user_id);
                })->pluck('user_id')->unique()->toArray();

                $insertData = collect($userIds)->map(function ($userId) use ($communication) {
                    return [
                        'uuid' => (string) Str::uuid(),
                        'type' => 'AppNotification',
                        'data' => json_encode([]),
                        'notifiable_id' => $userId,
                        'notifiable_type' => 'User',
                        'sender_user_id' => $communication->user_id,
                        'meta' => json_encode([
                            'template_code' => null,
                            'subject' => $communication->subject,
                            'content' => $communication->content,
                            'uuid' => (string) $communication->uuid,
                        ]),
                        'created_at' => now()->toDateTimeString(),
                    ];
                });

                Notification::insert($insertData->toArray());

                foreach ($chunk as $contact) {
                    $pushTokens = $contact->user?->pushTokens->pluck('token')->toArray() ?? [];

                    if (! count($pushTokens)) {
                        continue;
                    }

                    $jobs[] = new SendPushNotification([
                        'template' => $pushTemplate,
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
