<?php

namespace App\Jobs\Notifications\Communication;

use App\Concerns\SetConfigForJob;
use App\Models\Config\Template;
use App\Models\Notification;
use App\Models\User;
use App\Support\HasAudience;
use App\Support\MergeGuardianContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendTestPushNotification implements ShouldQueue
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

        $pushTemplate = new Template;
        $pushTemplate->type = 'push';
        $pushTemplate->code = 'dynamic-push-notification';
        $pushTemplate->subject = Arr::get($this->params, 'subject');
        $pushTemplate->content = Arr::get($this->params, 'content');

        $users = User::query()
            ->with('pushTokens')
            ->whereId(Arr::get($this->params, 'user_id'))
            ->get();

        $insertData = $users->map(function ($user) use ($pushTemplate) {
            return [
                'uuid' => (string) Str::uuid(),
                'type' => 'AppNotification',
                'data' => json_encode([]),
                'notifiable_id' => $user->id,
                'notifiable_type' => 'User',
                'sender_user_id' => $user->id,
                'meta' => json_encode([
                    'template_code' => null,
                    'subject' => $pushTemplate->subject,
                    'content' => $pushTemplate->content,
                ]),
                'created_at' => now()->toDateTimeString(),
            ];
        });

        Notification::insert($insertData->toArray());

        foreach ($users as $user) {
            $pushTokens = $user->pushTokens->pluck('token')->toArray() ?? [];

            if (! count($pushTokens)) {
                continue;
            }

            SendPushNotification::dispatchSync([
                'template' => $pushTemplate,
                'push_tokens' => $pushTokens,
                'variables' => [],
                'team_id' => $teamId,
            ]);
        }
    }
}
