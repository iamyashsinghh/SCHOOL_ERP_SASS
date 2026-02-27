<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class CleanNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:clean {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old notification';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Notification::query()
            ->where('created_at', '<', now()->subDays($this->option('days'))->toDateString())
            ->chunk(100, function ($chunk) {
                $chunk->each(function (Notification $notification) {
                    $notification->delete();
                });
            });

        $this->info('Old notification cleaned.');
    }
}
