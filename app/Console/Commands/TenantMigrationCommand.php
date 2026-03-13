<?php

namespace App\Console\Commands;

use App\Models\Central\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     * 
     * In shared DB mode, this simply runs migrations on the 'tenant' connection.
     * All schools share the same DB, so one migration run covers all schools.
     */
    protected $signature = 'tenant:migrate 
        {--fresh : Wipe and re-run all migrations}
        {--seed : Run seeders after migrating}
        {--force : Force in production}';

    protected $description = 'Run migrations on the shared tenant database (sass_school)';

    public function handle()
    {
        $this->info('🏫 Running migrations on shared tenant database...');

        $args = [
            '--database' => 'tenant',
            '--path' => 'database/migrations',
        ];

        if ($this->option('force')) {
            $args['--force'] = true;
        }

        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        $exitCode = Artisan::call($command, $args, $this->output);

        if ($exitCode === 0) {
            $this->info('✅ Tenant migrations completed successfully.');

            // Clear SassSchoolScope cache so new columns are detected
            \App\Scopes\SassSchoolScope::clearCache();

            if ($this->option('seed')) {
                $this->info('🌱 Running tenant database seeder...');
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'Database\Seeders\TenantDatabaseSeeder',
                    '--force' => true,
                ], $this->output);
            }

            // Show school count
            try {
                $schoolCount = School::on('central')->count();
                $this->info("📊 Total schools in platform: {$schoolCount}");
            } catch (\Exception $e) {
                $this->warn("⚠ Could not count schools (central DB may not be set up yet).");
            }
        } else {
            $this->error('❌ Migration failed!');
        }

        return $exitCode;
    }
}
