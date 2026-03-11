<?php

namespace App\Livewire\Central;

use Livewire\Component;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;
use App\Jobs\SyncTenantPermissions;
use App\Services\CentralAuditService;

class RolePermissionManager extends Component
{
    public $roles = [];
    public $permissions = []; // Structured by module -> permission_name -> [roles_assigned]
    
    public $newRoleName = '';
    public $isConfirmingSync = false;

    public function mount()
    {
        $this->loadData();
    }

    private function loadData()
    {
        $path = resource_path('var/permission.json');
        if (File::exists($path)) {
            $data = json_decode(File::get($path), true);
            $this->roles = $data['roles'] ?? [];
            $this->permissions = $data['permissions'] ?? [];
        }
    }

    public function render()
    {
        return view('livewire.central.role-permission-manager')
            ->layout('layouts.central', ['title' => 'Roles & Permissions', 'header' => 'Access Management']);
    }

    public function addRole()
    {
        $this->validate([
            'newRoleName' => 'required|alpha_dash|min:3|unique:roles,name'
        ]);

        $roleSlug = strtolower($this->newRoleName);
        if (!in_array($roleSlug, $this->roles)) {
            $this->roles[] = $roleSlug;
            $this->saveData();
            session()->flash('success', "Role '{$roleSlug}' added successfully.");
        } else {
            session()->flash('error', "Role '{$roleSlug}' already exists.");
        }

        $this->newRoleName = '';
    }

    public function removeRole($roleToRemove)
    {
        // Don't remove core admin role
        if ($roleToRemove === 'admin') {
            session()->flash('error', "Cannot remove the core Admin role.");
            return;
        }

        $this->roles = array_values(array_filter($this->roles, function($r) use ($roleToRemove) {
            return $r !== $roleToRemove;
        }));

        // Remove this role from all permission mappings
        foreach ($this->permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permissionName => $assignedRoles) {
                $this->permissions[$module][$permissionName] = array_values(array_filter($assignedRoles, function($r) use ($roleToRemove) {
                    return $r !== $roleToRemove;
                }));
            }
        }

        $this->saveData();
        session()->flash('success', "Role '{$roleToRemove}' removed.");
    }

    public function togglePermission($module, $permission, $role)
    {
        // Initialize if empty
        if (!isset($this->permissions[$module][$permission])) {
            $this->permissions[$module][$permission] = [];
        }

        $assignedRoles = $this->permissions[$module][$permission];
        $roleIndex = array_search($role, $assignedRoles);

        if ($roleIndex !== false) {
            // Remove role
            unset($assignedRoles[$roleIndex]);
        } else {
            // Add role
            $assignedRoles[] = $role;
        }

        $this->permissions[$module][$permission] = array_values($assignedRoles);
        $this->saveData();
    }

    private function saveData()
    {
        $path = resource_path('var/permission.json');
        
        // Ensure standard formatting
        $data = [
            'roles' => $this->roles,
            'permissions' => $this->permissions
        ];

        File::put($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function syncNow()
    {
        // Dispatch the background job
        SyncTenantPermissions::dispatch();

        app(CentralAuditService::class)->log(
            'permissions_synced',
            'System',
            0,
            ['action' => 'Triggered tenant permission sync']
        );

        $this->isConfirmingSync = false;
        session()->flash('success', 'Sync job dispatched! Active tenants are being updated in the background.');
    }
}
