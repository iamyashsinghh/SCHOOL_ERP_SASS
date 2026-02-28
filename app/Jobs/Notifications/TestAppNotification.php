<?php

namespace App\Jobs\Notifications;

use App\Concerns\SetConfigForJob;
use App\Events\TestAppNotificationEvent;
use App\Models\Tenant\Notification;
use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class TestAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $this->setConfig(modules: ['general', 'assets', 'system', 'social_network', 'notification']);

        $user = User::find(Arr::get($this->params, 'user_id'));

        Notification::create([
            'type' => 'TestNotification',
            'data' => [
                'name' => $user->name,
            ],
            'notifiable_id' => 1,
            'notifiable_type' => 'User',
            'meta' => [
                'template_code' => 'test-app-notification',
            ],
            'sender_user_id' => null,
        ]);

        broadcast(new TestAppNotificationEvent($user));
    }
}
