<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Seed Team
        $team = App\Models\Tenant\Team::forceCreate([
            'name' => 'Default'
        ]);
        \App\Helpers\SysHelper::setTeam($team->id);

        // 2. Seed Role & Permission
        \Spatie\Permission\Models\Role::create([
            'name' => 'admin',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'team_id' => null
        ]);

        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(AssignPermissionSeeder::class);

        // 3. Seed Config
        $this->call(TemplateSeeder::class);
        
        $config = App\Models\Tenant\Config\Config::firstOrCreate(['name' => 'system']);
        $config->value = [
            'show_setup_wizard' => true,
        ];
        $config->save();

        // 4. Seed User
        $user = App\Models\Tenant\User::forceCreate([
            'uuid'              => \Illuminate\Support\Str::uuid(),
            'name'              => 'Admin',
            'username'          => 'admin',
            'email'             => 'admin@admin.com',
            'password'          => bcrypt('password'), // Default password
            'email_verified_at' => now()->toDateTimeString(),
            'status'            => \App\Enums\UserStatus::ACTIVATED
        ]);

        $user->meta = array('is_default' => true);
        $user->save();

        $user->assignRole('admin');
    }
}
