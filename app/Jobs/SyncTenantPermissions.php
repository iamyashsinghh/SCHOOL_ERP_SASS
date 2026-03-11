<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Central\School;
use App\Services\TenantConnectionSwitcher;
use Illuminate\Support\Facades\Log;

class SyncTenantPermissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(TenantConnectionSwitcher $switcher): void
    {
        Log::info('SyncTenantPermissions job started.');

        $schools = School::on('central')->get();

        foreach ($schools as $school) {
            try {
                $switcher->switch($school);

                // Re-run the core Spatie Permission seeders for the tenant
                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\RoleSeeder',
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\PermissionSeeder',
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\AssignPermissionSeeder',
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                Log::info("Synced permissions for school: {$school->name} (ID: {$school->id})");

            } catch (\Exception $e) {
                Log::error("Failed to sync permissions for school ID {$school->id}: " . $e->getMessage());
            } finally {
                // Purge tenant connection to prevent memory leaks in the daemon job
                \Illuminate\Support\Facades\DB::purge('tenant');
            }
        }

        Log::info('SyncTenantPermissions job completed.');
    }
}
