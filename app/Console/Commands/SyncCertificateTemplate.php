<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncCertificateTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:certificate-template {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync certificate template';

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
        $force = $this->option('force');

        \Artisan::call('db:seed', ['--class' => \Database\Seeders\Academic\CertificateTemplateSeeder::class, '--force' => $force ? true : false]);

        $this->info('Certificate template synced.');
    }
}
