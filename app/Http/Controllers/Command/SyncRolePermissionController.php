<?php

namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;

class SyncRolePermissionController extends Controller
{
    public function __invoke(Request $request)
    {
        $role = $request->query('role');

        if (! $role) {
            return 'Role is required.';
        }

        $role = Role::query()
            ->where('name', $role)
            ->firstOrFail();

        $assignedPermissions = $role->permissions->count();

        if ($assignedPermissions > 0 && ! $request->query('force')) {
            return 'Role already has permissions.';
        }

        $roleName = $role->name;

        $acl = Arr::getVar('permission');

        $system_permissions = Arr::get($acl, 'permissions', []);

        $permissions = collect($system_permissions)
            ->flatMap(function ($modulePermissions) use ($roleName) {
                return collect($modulePermissions)
                    ->filter(fn ($roles) => in_array($roleName, $roles))
                    ->keys();
            })
            ->values()
            ->toArray();

        \DB::table('role_has_permissions')->where('role_id', $role->id)->delete();

        foreach ($permissions as $permission) {
            $role->givePermissionTo($permission);
        }

        return view('index', ['message' => 'Role permission synced.']);
    }
}
