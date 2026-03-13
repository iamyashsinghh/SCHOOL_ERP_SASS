<?php

namespace Database\Seeders;

use App\Models\Tenant\Team;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Seeds roles, permissions, and admin user for a NEWLY PROVISIONED school.
 * 
 * This is used instead of TenantDatabaseSeeder when adding a school 
 * to the shared database (sass_school_id based isolation).
 * 
 * Organization & Team are already created by TenantCreatorService.
 */
class TenantSchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sassSchoolId = config('tenant_setup.sass_school_id');
        $teamId = config('tenant_setup.team_id');

        // 0. Forget cached permissions
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Set team context for Spatie permissions
        \App\Helpers\SysHelper::setTeam($teamId);

        // 2. Run Role/Permission Seeders (these create roles/permissions with team_id)
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(AssignPermissionSeeder::class);

        // 3. Seed Templates & Configs
        $this->call(TemplateSeeder::class);

        // 4. Create Admin User
        $admin = User::forceCreate([
            'uuid'              => (string) Str::uuid(),
            'name'              => config('tenant_setup.admin_name', 'Administrator'),
            'username'          => config('tenant_setup.admin_username', 'admin'),
            'email'             => config('tenant_setup.admin_email', 'admin@example.com'),
            'password'          => Hash::make(config('tenant_setup.admin_password', 'password')),
            'status'            => \App\Enums\UserStatus::ACTIVATED,
            'email_verified_at' => now(),
            'meta'              => ['is_default' => true, 'current_team_id' => $teamId],
            'sass_school_id'    => $sassSchoolId,
        ]);

        // 5. Assign Admin Role
        $adminRole = Role::whereName('admin')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }
    }
}
