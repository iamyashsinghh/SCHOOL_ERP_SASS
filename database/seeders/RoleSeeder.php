<?php

namespace Database\Seeders;

use App\Models\Tenant\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $acl = Arr::getVar('permission');
        $teamId = config('tenant_setup.team_id');

        // If teamId is set, we only seed for that specific team (Provisioning mode)
        // Otherwise we seed for all teams (Standard/Initial seeding mode)
        $teams = $teamId ? Team::whereId($teamId)->get() : Team::get();

        foreach ($teams as $team) {
            $existing_roles = Role::query()
                ->where(function ($q) use ($team) {
                    $q->whereNull('team_id')
                        ->orWhere('team_id', $team->id);
                })
                ->get()
                ->pluck('name')
                ->all();
            $system_roles = Arr::get($acl, 'roles', []);

            $new_roles = array_diff($system_roles, $existing_roles);

            $insert_roles = [];
            foreach ($new_roles as $role) {
                $insert_roles[] = [
                    'uuid' => (string) Str::uuid(),
                    'name' => $role,
                    'guard_name' => 'web',
                    'team_id' => $role == 'admin' ? null : $team->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Role::insert($insert_roles);
        }
    }
}
