<?php

namespace App\Services;

use App\Models\Central\School;
use App\Models\Central\Domain;
use App\Models\Tenant\Organization;
use App\Models\Tenant\Team;
use App\Models\Tenant\User;
use App\Scopes\SassSchoolScope;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantCreatorService
{
    /**
     * Create a new school in the shared database.
     * 
     * Instead of creating a new MySQL database, we create Team/Organization
     * records in the shared sass_school DB with the school's sass_school_id.
     * 
     * @param array $data Contains school, domain and admin details.
     * @return School
     */
    public function create(array $data)
    {
        $school = null;

        set_time_limit(0);

        try {
            \Log::info("Starting provisioning for school: " . $data['name']);

            // 0. Pre-flight Validation
            $this->validate($data);

            // 1. Create School Record in Central DB
            $school = School::create([
                'ministry_id' => $data['ministry_id'],
                'province_id' => $data['province_id'],
                'sub_division_id' => $data['sub_division_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'status' => 'active',
                'contact_email' => $data['admin_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'admin_username' => $data['admin_username'] ?? 'admin',
                'admin_password_reference' => $data['admin_password'] ?? 'password',
            ]);

            // 2. Create Domain mapping
            Domain::create([
                'school_id' => $school->id,
                'domain' => $data['domain'],
            ]);

            // 3. Set sass_school_id context for the new school
            //    This ensures all records created below get the correct sass_school_id
            $originalConnection = DB::getDefaultConnection();
            DB::setDefaultConnection('tenant');
            app()->instance('sass_school_id', $school->id);

            // Clear the SassSchoolScope cache so it doesn't filter during seeding
            \App\Scopes\SassSchoolScope::clearCache();

            // 4. Create Organization in Shared DB
            $organization = Organization::on('tenant')->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'code' => Str::slug($data['name']),
                'email' => $data['admin_email'] ?? 'admin@' . $data['domain'],
                'sass_school_id' => $school->id,
            ]);

            // 5. Create Team in Shared DB
            $team = Team::on('tenant')->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Main Team',
                'code' => 'main',
                'organization_id' => $organization->id,
                'sass_school_id' => $school->id,
            ]);

            // 6. Set Spatie Permission Team Context
            \App\Helpers\SysHelper::setTeam($team->id);

            // 7. Seed Roles & Permissions for this school
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            
            // Run seeders with sass_school_id context
            config([
                'tenant_setup' => [
                    'org_name' => $school->name,
                    'admin_name' => $data['admin_name'] ?? 'Administrator',
                    'admin_username' => $data['admin_username'] ?? 'admin',
                    'admin_email' => $data['admin_email'] ?? 'admin@' . $data['domain'],
                    'admin_password' => $data['admin_password'] ?? 'password',
                    'sass_school_id' => $school->id,
                    'team_id' => $team->id,
                ]
            ]);

            $exitCode = Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'Database\Seeders\TenantSchoolSeeder',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                \Log::error("Seeding failed for school " . $school->name . ": " . Artisan::output());
                throw new \Exception("Seeding failed: " . Artisan::output());
            }

            // 8. Save Timezone to Tenant Config
            if (!empty($data['timezone'])) {
                DB::connection('tenant')->table('configs')->updateOrInsert(
                    ['name' => 'system', 'sass_school_id' => $school->id],
                    [
                        'value' => json_encode(['timezone' => $data['timezone']]),
                        'sass_school_id' => $school->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 9. Create Storage Directory
            $storagePath = storage_path('app/tenants/' . $school->id);
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            \Log::info("Provisioning completed successfully for: " . $school->name);

            return $school;

        } catch (\Exception $e) {
            // ROLLBACK — Delete all records with this sass_school_id
            if ($school) {
                $this->deleteSchoolData($school);
            }

            throw $e;
        } finally {
            if (isset($originalConnection)) {
                DB::setDefaultConnection($originalConnection);
            }
        }
    }

    /**
     * Delete a school and all associated resources.
     * 
     * @param School $school
     * @return void
     */
    public function delete(School $school)
    {
        // 1. Delete all records in shared DB with this sass_school_id
        $this->deleteSchoolData($school);

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

        // 3. Delete Domains and School Record from Central DB
        Domain::where('school_id', $school->id)->delete();
        $school->delete();
    }

    /**
     * Delete all tenant data for a school from the shared database.
     */
    protected function deleteSchoolData(School $school): void
    {
        $tables = \Illuminate\Support\Facades\Schema::connection('tenant')->getTableListing();
        $excludeTables = ['migrations', 'failed_jobs', 'jobs', 'job_batches', 'personal_access_tokens', 'sessions'];

        foreach ($tables as $table) {
            if (in_array($table, $excludeTables)) {
                continue;
            }

            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn($table, 'sass_school_id')) {
                DB::connection('tenant')->table($table)->where('sass_school_id', $school->id)->delete();
            }
        }

        // Also delete from central
        Domain::where('school_id', $school->id)->delete();
        if ($school->exists) {
            $school->delete();
        }
    }

    /**
     * Pre-flight validation to prevent generic SQL errors.
     */
    protected function validate(array $data)
    {
        // 1. Central DB Checks
        if (School::where('code', $data['code'])->exists()) {
            throw new \Exception("The School Code '{$data['code']}' is already in use by another school.");
        }

        if (Domain::where('domain', $data['domain'])->exists()) {
            throw new \Exception("The Domain '{$data['domain']}' is already mapped to another school.");
        }

        // 2. Tenant DB Checks (Admin User)
        $adminEmail = $data['admin_email'] ?? 'admin@' . $data['domain'];
        $adminUsername = $data['admin_username'] ?? 'admin';

        $existingUser = User::on('tenant')
            ->where('email', $adminEmail)
            ->orWhere('username', $adminUsername)
            ->first();

        if ($existingUser) {
            if ($existingUser->email === $adminEmail) {
                throw new \Exception("The Administrator email '{$adminEmail}' is already registered in the system.");
            }
            if ($existingUser->username === $adminUsername) {
                throw new \Exception("The Administrator username '{$adminUsername}' is already taken.");
            }
        }
    }
}
