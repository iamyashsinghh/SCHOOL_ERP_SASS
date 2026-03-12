<?php

namespace App\Services;

use App\Models\Central\School;
use App\Models\Central\Domain;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class TenantCreatorService
{
    /**
     * Create a new tenant with database and default configuration.
     * 
     * @param array $data Contains school, domain and admin details.
     * @return School
     */
    public function create(array $data)
    {
        $school = null;
        $dbCreated = false;

        // Increase timeout for long migrations on local/Windows envs
        set_time_limit(0);

        try {
            \Log::info("Starting provisioning for school: " . $data['name']);
            // 1. Create School Record in Central DB
            $school = School::create([
                'ministry_id' => $data['ministry_id'],
                'province_id' => $data['province_id'],
                'sub_division_id' => $data['sub_division_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'db_name' => $data['db_name'] ?? 'school_sass_' . Str::lower($data['code']),
                'db_username' => config('database.connections.mysql.username', env('DB_USERNAME', 'root')),
                'db_password' => Crypt::encryptString(config('database.connections.mysql.password', env('DB_PASSWORD', ''))),
                'storage_prefix' => Str::slug($data['name']),
                'status' => 'active',
                'admin_username' => $data['admin_username'] ?? 'admin',
                'admin_password_reference' => $data['admin_password'] ?? 'password',
            ]);

            // 2. Create Domain mapping
            Domain::create([
                'school_id' => $school->id,
                'domain' => $data['domain'],
            ]);

            // 3. Create the Physical Database
            $this->createDatabase($school->db_name);
            $dbCreated = true;

            // 4. Configure Tenant Connection for Migration/Seeding
            $this->configureTenantConnection($school);

            // 5. Run Migrations
            \Log::info("Running tenant migrations for: " . $school->db_name);
            $migrateCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
            ]);

            if ($migrateCode !== 0) {
                \Log::error("Migration failed for " . $school->db_name . ": " . Artisan::output());
                throw new \Exception("Migration failed: " . Artisan::output());
            }

            // 6. Run Seeding
            \Log::info("Running tenant seeding for: " . $school->db_name);
            config([
                'tenant_setup' => [
                    'org_name' => $school->name,
                    'admin_name' => $data['admin_name'] ?? 'Administrator',
                    'admin_username' => $data['admin_username'] ?? 'admin',
                    'admin_email' => $data['admin_email'] ?? 'admin@' . $data['domain'],
                    'admin_password' => $data['admin_password'] ?? 'password',
                ]
            ]);

            $exitCode = Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'Database\Seeders\TenantDatabaseSeeder',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                \Log::error("Seeding failed for " . $school->db_name . ": " . Artisan::output());
                throw new \Exception("Seeding failed: " . Artisan::output());
            }

            \Log::info("Provisioning completed successfully for: " . $school->db_name);

            // 7. Save Selected Timezone to Tenant Config
            if (!empty($data['timezone'])) {
                \Illuminate\Support\Facades\DB::connection('tenant')->table('configs')->updateOrInsert(
                    ['name' => 'system'],
                    [
                        'value' => json_encode(['timezone' => $data['timezone']]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 8. Create Storage Directory
            $storagePath = storage_path('app/tenants/' . $school->id);
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            return $school;

        } catch (\Exception $e) {
            // ROLLBACK / CLEANUP
            if ($dbCreated && isset($school->db_name)) {
                DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$school->db_name}`");
            }

            if ($school) {
                Domain::where('school_id', $school->id)->delete();
                $school->delete();
            }

            throw $e;
        }
    }

    /**
     * Create the MySQL database physically.
     */
    protected function createDatabase($dbName)
    {
        $charset = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    /**
     * Switch connection configuration on the fly.
     */
    protected function configureTenantConnection(School $school)
    {
        Config::set('database.connections.tenant.database', $school->db_name);
        Config::set('database.connections.tenant.username', $school->db_username);
        Config::set('database.connections.tenant.password', Crypt::decryptString($school->db_password));

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Delete a school and all associated resources.
     * 
     * @param School $school
     * @return void
     */
    public function delete(School $school)
    {
        // 1. Drop Database
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$school->db_name}`");

        // 2. Cleanup Storage
        $storagePaths = [
            storage_path('app/tenants/' . $school->id),
            storage_path('app/public/tenants/' . $school->id),
        ];

        foreach ($storagePaths as $path) {
            if (File::exists($path)) {
                File::deleteDirectory($path);
            }
        }

        // 3. Delete Domains and School Record (Cascaded by relationships if set, else manual)
        Domain::where('school_id', $school->id)->delete();
        $school->delete();
    }
}
