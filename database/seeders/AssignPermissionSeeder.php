<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AssignPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $acl = Arr::getVar('permission');
        $system_permissions = Arr::get($acl, 'permissions', []);
        $teamId = config('tenant_setup.team_id');

        // Only process roles for the current team (plus the global admin role)
        $roles = Role::where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId);
            if (!$teamId) {
                $q->orWhereNull('team_id');
            }
        })->where('name', '!=', 'admin')->get();
        
        $permissions = Permission::all();

        $role_permission = [];
        foreach ($system_permissions as $permission_group) {
            foreach ($permission_group as $name => $assigned_roles) {
                foreach ($assigned_roles as $roleName) {
                    $permission = $permissions->firstWhere('name', $name);
                    if (!$permission) continue;

                    foreach ($roles->where('name', $roleName) as $role) {
                        // Check if already exists to prevent Duplicate Entry error in shared DB
                        $exists = \DB::table('role_has_permissions')
                            ->where('permission_id', $permission->id)
                            ->where('role_id', $role->id)
                            ->exists();

                        if (!$exists) {
                            $role_permission[] = [
                                'permission_id' => $permission->id,
                                'role_id' => $role->id,
                            ];
                        }
                    }
                }
            }
        }

        if (count($role_permission)) {
            \DB::table('role_has_permissions')->insert($role_permission);
        }
    }
}
