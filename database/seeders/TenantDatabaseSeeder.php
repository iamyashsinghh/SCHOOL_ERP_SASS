<?php

namespace Database\Seeders;

use App\Models\Tenant\Organization;
use App\Models\Tenant\Team;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Forget cached permissions
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Create Initial Organization
        $organization = Organization::create([
            'uuid' => (string) Str::uuid(),
            'name' => config('tenant_setup.org_name', 'Default Organization'),
            'code' => Str::slug(config('tenant_setup.org_name', 'default')),
            'email' => config('tenant_setup.admin_email', 'admin@example.com'),
        ]);

        // 2. Create Initial Team
        $team = Team::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Main Team',
            'code' => 'main',
            'organization_id' => $organization->id,
        ]);

        // 3. Set current team for Spatie
        \App\Helpers\SysHelper::setTeam($team->id);

        // 4. Run Core Role/Permission Seeders
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(AssignPermissionSeeder::class);

        // 5. Seed Templates & Other Configs
        $this->call(TemplateSeeder::class);

        // 6. Create Admin User (using forceCreate to bypass fillable)
        $admin = User::forceCreate([
            'uuid'              => (string) Str::uuid(),
            'name'              => config('tenant_setup.admin_name', 'Administrator'),
            'username'          => config('tenant_setup.admin_username', 'admin'),
            'email'             => config('tenant_setup.admin_email', 'admin@example.com'),
            'password'          => Hash::make(config('tenant_setup.admin_password', 'password')),
            'status'            => \App\Enums\UserStatus::ACTIVATED,
            'email_verified_at' => now(),
            'meta'              => ['is_default' => true, 'current_team_id' => $team->id],
        ]);

        // 7. Assign Admin Role (Admin role usually has team_id = null in this system)
        $adminRole = Role::whereName('admin')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }
    }
}
