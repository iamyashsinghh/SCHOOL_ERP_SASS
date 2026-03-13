<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\School;
use App\Models\Central\SubDivision;
use App\Models\Central\Domain;
use App\Services\TenantCreatorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SchoolManager extends Component
{
    public $name, $sub_division_id, $domain;
    public $admin_name, $admin_username, $admin_email, $admin_password;
    public $contact_phone, $address, $timezone;
    public $isCreating = false;
    public $message;

    public function mount()
    {
        //
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
                'code' => strtoupper(Str::substr(preg_replace('/[^a-zA-Z0-9]/', '', $this->name), 0, 8)) . Str::random(4),
                'ministry_id' => $subDivision->province->ministry_id,
                'province_id' => $subDivision->province_id,
                'sub_division_id' => $this->sub_division_id,
                'domain' => $this->domain,
                'contact_phone' => $this->contact_phone,
                'address' => $this->address,
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

        // Clear tenant cache so IdentifyTenant picks up new status
        Cache::forget("tenant_school_{$id}");

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

            session()->flash('success', "School {$schoolName} and its data have been deleted successfully.");
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
        
        // Set sass_school_id context to query this school's users
        app()->instance('sass_school_id', $schoolId);
        
        $this->tenantUsers = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('users')
            ->where('sass_school_id', $schoolId)
            ->select('id', 'name', 'email', 'username')
            ->get()
            ->toArray();
        $this->resetEditForm();
    }

    public function closeEditUsers()
    {
        $this->isEditingUsers = false;
        $this->editingSchoolId = null;
        $this->tenantUsers = [];
        $this->selectedUserId = null;
        $this->resetEditForm();
    }

    public function updatedSelectedUserId()
    {
        if (!$this->selectedUserId) {
            $this->resetEditForm();
            return;
        }
        
        $user = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('users')
            ->where('id', $this->selectedUserId)
            ->where('sass_school_id', $this->editingSchoolId)
            ->first();

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

        $updateData = [
            'name' => $this->editUserName,
            'email' => $this->editUserEmail,
            'username' => $this->editUserUsername,
        ];

        if (!empty($this->editUserPassword)) {
            $updateData['password'] = \Illuminate\Support\Facades\Hash::make($this->editUserPassword);
        }

        \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('users')
            ->where('id', $this->selectedUserId)
            ->where('sass_school_id', $this->editingSchoolId)
            ->update($updateData);

        // Update admin reference in central if needed
        $school = School::on('central')->findOrFail($this->editingSchoolId);
        if ($school->admin_username === $this->editUserUsername || $school->admin_username === $this->editUserUsername) {
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
