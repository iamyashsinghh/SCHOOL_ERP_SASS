<?php

namespace App\Console\Commands;

use App\Jobs\Notifications\SendReminderNotification;
use App\Models\Tenant\Reminder;
use Illuminate\Console\Command;

class SendReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder';

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
        Reminder::query()
            ->where('date', '=', now()->toDateString())
            ->chunk(20, function ($chunk) {
                $chunk->each(function (Reminder $reminder) {
                    SendReminderNotification::dispatch([
                        'reminder_id' => $reminder->id,
                    ]);
                });
            });

        Reminder::query()
            ->whereDate(
                \DB::raw('DATE_SUB(date, INTERVAL notify_before DAY)'),
                today()->toDateString()
            )
            ->chunk(20, function ($chunk) {
                $chunk->each(function (Reminder $reminder) {
                    SendReminderNotification::dispatch([
                        'reminder_id' => $reminder->id,
                        'notify_before' => true,
                    ]);
                });
            });

        $this->info('Reminder sent.');
    }
}
