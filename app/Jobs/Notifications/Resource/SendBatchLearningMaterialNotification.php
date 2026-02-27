<?php

namespace App\Jobs\Notifications\Resource;

use App\Concerns\SetConfigForJob;
use App\Models\Config\Template;
use App\Models\Notification;
use App\Models\Resource\LearningMaterial;
use App\Models\Student\Student;
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

class SendBatchLearningMaterialNotification implements ShouldQueue
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

        $templateCode = 'learning-material-published';

        $templates = Template::query()
            ->whereCode($templateCode)
            ->whereNotNull('enabled_at')
            ->get();

        if (! $templates->count()) {
            return;
        }

        $learningMaterial = LearningMaterial::query()
            ->with('records', 'audiences')
            ->findOrFail(Arr::get($this->params, 'learning_material_id'));

        $variables = [
            'title' => $learningMaterial->title,
            'datetime' => $learningMaterial->published_at->formatted,
        ];

        $batchIds = [];
        $studentIds = [];

        foreach ($learningMaterial->records as $record) {
            $batchIds[] = $record->batch_id;
        }

        foreach ($learningMaterial->audiences as $audience) {
            if ($audience->audienceable_type === 'Student') {
                $studentIds[] = $audience->audienceable_id;
            }
        }

        $students = Student::query()
            ->select('students.id', 'students.contact_id')
            ->byPeriod($learningMaterial->period_id)
            ->join('contacts', 'students.contact_id', '=', 'contacts.id')
            ->where(function ($q) use ($studentIds, $batchIds) {
                $q->whereIn('students.id', $studentIds)
                    ->orWhereIn('students.batch_id', $batchIds);
            })
            ->get();

        $contactQuery = $this->mergeGuardianContact($students->pluck('contact_id')->toArray(), 'query');

        $jobs = [];

        $contactQuery
            ->with('user:id', 'user.pushTokens:id,user_id,type,token')
            ->chunk(100, function ($chunk) use (&$jobs, $templates, $templateCode, $variables, $teamId, $learningMaterial, $senderUserId) {
                $userIds = $chunk->filter(function ($contact) {
                    return ! is_null($contact->user_id);
                })->pluck('user_id')->unique()->toArray();

                $insertData = collect($userIds)->map(function ($userId) use ($variables, $learningMaterial, $templateCode, $senderUserId) {
                    return [
                        'uuid' => (string) Str::uuid(),
                        'type' => 'LearningMaterial',
                        'data' => json_encode($variables),
                        'notifiable_id' => $userId,
                        'notifiable_type' => 'User',
                        'sender_user_id' => $senderUserId,
                        'meta' => json_encode([
                            'template_code' => $templateCode,
                            'uuid' => (string) $learningMaterial->uuid,
                        ]),
                        'created_at' => now()->toDateTimeString(),
                    ];
                });

                Notification::insert($insertData->toArray());

                foreach ($chunk as $contact) {
                    $pushTokens = $contact->user?->pushTokens->pluck('token')->toArray() ?? [];

                    $jobs[] = new SendLearningMaterialNotification([
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
