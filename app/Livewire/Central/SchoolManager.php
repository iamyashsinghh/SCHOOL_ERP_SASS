<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\School;
use App\Models\Central\SubDivision;
use App\Models\Central\Domain;
use App\Services\TenantCreatorService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SchoolManager extends Component
{
    public $name, $sub_division_id, $db_name, $db_username, $db_password, $storage_prefix, $domain;
    public $admin_name, $admin_username, $admin_email, $admin_password;
    public $timezone;
    public $isCreating = false;
    public $message;

    public function mount()
    {
        $this->db_username = env('DB_USERNAME', 'root');
        $this->db_password = env('DB_PASSWORD', '');
    }

    // Edit User functionality
    public $isEditingUsers = false;
    public $editingSchoolId;
    public $tenantUsers = [];
    public $selectedUserId;
    public $editUserName, $editUserEmail, $editUserUsername, $editUserPassword;

    protected $rules = [
        'name' => 'required|min:3|unique:central.schools,name',
        'sub_division_id' => 'required|exists:central.sub_divisions,id',
        'db_name' => 'required|unique:central.schools,db_name',
        'db_username' => 'required',
        'db_password' => 'nullable',
        'storage_prefix' => 'required|unique:central.schools,storage_prefix',
        'domain' => 'required|unique:central.domains,domain',
        'admin_name' => 'required|min:3',
        'admin_username' => 'required|min:3|alpha_dash',
        'admin_email' => 'required|email',
        'admin_password' => 'required|min:8',
        'timezone' => 'required|string',
    ];

    public function render()
    {
        $currentUser = auth('central')->user();
        $query = School::on('central')->with('subDivision.province.ministry', 'domains');
        $sdQuery = SubDivision::on('central')->with('province.ministry');

        if ($currentUser->isPlatformOwner()) {
            // No filter
        } elseif ($currentUser->isMinistryAdmin()) {
            $query->whereHas('subDivision.province', function($q) use ($currentUser) {
                $q->where('ministry_id', $currentUser->entity_id);
            });
            $sdQuery->whereHas('province', function($q) use ($currentUser) {
                $q->where('ministry_id', $currentUser->entity_id);
            });
        } elseif ($currentUser->isProvinceAdmin()) {
            $query->whereHas('subDivision', function($q) use ($currentUser) {
                $q->where('province_id', $currentUser->entity_id);
            });
            $sdQuery->where('province_id', $currentUser->entity_id);
        } elseif ($currentUser->isSubdivisionAdmin()) {
            $query->where('sub_division_id', $currentUser->entity_id);
            $sdQuery->where('id', $currentUser->entity_id);
        }

        return view('livewire.central.school-manager', [
            'schools' => $query->latest()->get(),
            'subDivisions' => $sdQuery->get()
        ])->layout('layouts.central', ['title' => 'School Management', 'header' => 'Schools']);
    }

    public function updatedName($value)
    {
        if (empty($value)) {
            $this->db_name = '';
            $this->storage_prefix = '';
            return;
        }
        $slug = Str::lower(Str::slug($value, '_'));
        $this->db_name = 'school_' . $slug;
        $this->storage_prefix = $slug;
    }

    public function updatedDomain($value)
    {
        if (empty($this->admin_email) && !empty($value)) {
            $this->admin_email = 'admin@' . $value;
        }
    }

    public function provision()
    {
        $this->validate();

        $service = app(TenantCreatorService::class);

        $subDivision = SubDivision::on('central')->with('province')->findOrFail($this->sub_division_id);

        try {
            $school = $service->create([
                'name' => $this->name,
                'code' => strtoupper(\Illuminate\Support\Str::substr(preg_replace('/[^a-zA-Z0-9]/', '', $this->name), 0, 8)) . \Illuminate\Support\Str::random(4),
                'ministry_id' => $subDivision->province->ministry_id,
                'province_id' => $subDivision->province_id,
                'sub_division_id' => $this->sub_division_id,
                'db_name' => $this->db_name,
                'db_username' => $this->db_username,
                'db_password' => Crypt::encryptString($this->db_password ?? ''),
                'storage_prefix' => $this->storage_prefix,
                'domain' => $this->domain,
                'admin_name' => $this->admin_name,
                'admin_username' => $this->admin_username,
                'admin_email' => $this->admin_email,
                'admin_password' => $this->admin_password,
                'timezone' => $this->timezone,
            ]);

            app(\App\Services\CentralAuditService::class)->log(
                'school_provisioned',
                'School',
                $school->id,
                ['name' => $this->name, 'domain' => $this->domain]
            );

            session()->flash('success', "School {$school->name} provisioned successfully!");
            $this->reset();
            $this->isCreating = false;
        } catch (\Exception $e) {
            $this->message = "Provisioning failed: " . $e->getMessage();
        }
    }

    public function toggleStatus($id)
    {
        $school = School::on('central')->findOrFail($id);
        $school->status = $school->status === 'active' ? 'suspended' : 'active';
        $school->save();

        app(\App\Services\CentralAuditService::class)->log(
            'school_status_toggled',
            'School',
            $school->id,
            ['new_status' => $school->status]
        );
    }

    public function deleteSchool($id)
    {
        $school = School::on('central')->findOrFail($id);
        $schoolName = $school->name;

        try {
            app(TenantCreatorService::class)->delete($school);
            
            app(\App\Services\CentralAuditService::class)->log(
                'school_deleted',
                'School',
                $id,
                ['name' => $schoolName]
            );

            session()->flash('success', "School {$schoolName} and its resources have been deleted successfully.");
        } catch (\Exception $e) {
            $this->message = "Deletion failed: " . $e->getMessage();
        }
    }

    public function impersonate($id)
    {
        $school = School::on('central')->with('domains')->findOrFail($id);
        
        if ($school->domains->isEmpty()) {
            session()->flash('error', "No domain mapped to this school. Cannot login.");
            return;
        }

        $domain = $school->domains->first()->domain;
        $token = Str::random(64);
        
        // Store the token in cache for 1 minute
        Cache::put("sso_token_{$token}", [
            'school_id' => $school->id,
            'admin_username' => $school->admin_username
        ], now()->addMinutes(1));

        $protocol = request()->secure() ? 'https://' : 'http://';
        $port = request()->getPort() != 80 && request()->getPort() != 443 ? ':' . request()->getPort() : '';
        $url = "{$protocol}{$domain}{$port}/app/sso?token={$token}";
        
        $this->js("window.open('{$url}', '_blank')");
    }

    public function openEditUsers($schoolId)
    {
        $this->editingSchoolId = $schoolId;
        $this->isEditingUsers = true;
        $this->isCreating = false;
        
        $school = School::on('central')->findOrFail($schoolId);
        app(\App\Services\TenantConnectionSwitcher::class)->switch($school);
        
        $this->tenantUsers = \Illuminate\Support\Facades\DB::connection('tenant')->table('users')->select('id', 'name', 'email', 'username')->get()->toArray();
        $this->resetEditForm();
    }

    public function closeEditUsers()
    {
        $this->isEditingUsers = false;
        $this->editingSchoolId = null;
        $this->tenantUsers = [];
        $this->selectedUserId = null;
        $this->resetEditForm();
        \Illuminate\Support\Facades\DB::purge('tenant');
    }

    public function updatedSelectedUserId()
    {
        if (!$this->selectedUserId) {
            $this->resetEditForm();
            return;
        }
        
        $school = School::on('central')->findOrFail($this->editingSchoolId);
        app(\App\Services\TenantConnectionSwitcher::class)->switch($school);
        
        $user = \Illuminate\Support\Facades\DB::connection('tenant')->table('users')->where('id', $this->selectedUserId)->first();
        if ($user) {
            $this->editUserName = $user->name;
            $this->editUserEmail = $user->email;
            $this->editUserUsername = $user->username;
            $this->editUserPassword = '';
        }
    }

    public function updateUser()
    {
        $this->validate([
            'selectedUserId' => 'required',
            'editUserName' => 'required|min:3',
            'editUserEmail' => 'required|email',
            'editUserUsername' => 'required|min:3|alpha_dash',
        ]);

        $school = School::on('central')->findOrFail($this->editingSchoolId);
        app(\App\Services\TenantConnectionSwitcher::class)->switch($school);

        $updateData = [
            'name' => $this->editUserName,
            'email' => $this->editUserEmail,
            'username' => $this->editUserUsername,
        ];

        if (!empty($this->editUserPassword)) {
            $updateData['password'] = \Illuminate\Support\Facades\Hash::make($this->editUserPassword);
        }

        \Illuminate\Support\Facades\DB::connection('tenant')->table('users')->where('id', $this->selectedUserId)->update($updateData);

        // Check if we are updating the primary admin user's credentials
        $dbUsername = \Illuminate\Support\Facades\DB::connection('tenant')->table('users')->where('id', $this->selectedUserId)->value('username');
        if ($school->admin_username === $this->editUserUsername || $school->admin_username === $dbUsername) {
            $school->admin_username = $this->editUserUsername;
            if (!empty($this->editUserPassword)) {
                $school->admin_password_reference = $this->editUserPassword;
            }
            $school->save();
        }

        session()->flash('success', "User updated successfully.");
        $this->closeEditUsers();
    }

    private function resetEditForm()
    {
        $this->editUserName = '';
        $this->editUserEmail = '';
        $this->editUserUsername = '';
        $this->editUserPassword = '';
    }
}
