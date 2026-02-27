<?php

namespace App\Jobs\Notifications\Communication;

use App\Actions\SendPushNotification as SendPushNotificationAction;
use App\Concerns\SetConfigForJob;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendPushNotification implements ShouldQueue
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

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'notification']);

        $variables = Arr::get($this->params, 'variables');
        $pushTokens = Arr::get($this->params, 'push_tokens');
        $template = Arr::get($this->params, 'template');

        (new SendPushNotificationAction)->execute(
            pushTokens: $pushTokens,
            template: $template,
            variables: $variables,
        );
    }
}
