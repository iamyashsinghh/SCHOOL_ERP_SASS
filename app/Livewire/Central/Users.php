<?php

namespace App\Livewire\Central;

use App\Models\Central\CentralUser;
use App\Models\Central\Ministry;
use App\Models\Central\Province;
use App\Models\Central\SubDivision;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Users extends Component
{
    public $name, $email, $password, $role, $entity_id;
    public $userId;
    public $isEditing = false;
    public $showModal = false;

    // Entities for selection
    public $ministries = [], $provinces = [], $subDivisions = [];

    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            'email' => 'required|email|unique:central.central_users,email,' . $this->userId,
            'password' => $this->userId ? 'nullable|min:6' : 'required|min:6',
            'role' => 'required|in:platform_owner,ministry_admin,province_admin,subdivision_admin',
            'entity_id' => 'required_unless:role,platform_owner',
        ];
    }

    public function mount()
    {
        $this->loadEntities();
    }

    public function loadEntities()
    {
        $currentUser = auth('central')->user();

        if ($currentUser->isPlatformOwner()) {
            $this->ministries = Ministry::on('central')->get();
            $this->provinces = Province::on('central')->get();
            $this->subDivisions = SubDivision::on('central')->get();
        } elseif ($currentUser->isMinistryAdmin()) {
            $this->provinces = Province::on('central')->where('ministry_id', $currentUser->entity_id)->get();
            $this->subDivisions = SubDivision::on('central')->whereIn('province_id', $this->provinces->pluck('id'))->get();
        } elseif ($currentUser->isProvinceAdmin()) {
            $this->subDivisions = SubDivision::on('central')->where('province_id', $currentUser->entity_id)->get();
        }
    }

    public function render()
    {
        return view('livewire.central.users', [
            'users' => $this->getScopedUsers()
        ])->layout('layouts.central', ['title' => 'User Management', 'header' => 'Users']);
    }

    public function getScopedUsers()
    {
        $currentUser = auth('central')->user();
        $query = CentralUser::on('central');

        if ($currentUser->isPlatformOwner()) {
            return $query->latest()->get();
        }

        if ($currentUser->isMinistryAdmin()) {
            $provinceIds = Province::on('central')->where('ministry_id', $currentUser->entity_id)->pluck('id');
            $subDivisionIds = SubDivision::on('central')->whereIn('province_id', $provinceIds)->pluck('id');

            return $query->where(function($q) use ($currentUser, $provinceIds, $subDivisionIds) {
                $q->where('role', CentralUser::ROLE_MINISTRY_ADMIN)->where('entity_id', $currentUser->entity_id)
                  ->orWhere(function($sq) use ($provinceIds) {
                      $sq->where('role', CentralUser::ROLE_PROVINCE_ADMIN)->whereIn('entity_id', $provinceIds);
                  })
                  ->orWhere(function($sq) use ($subDivisionIds) {
                      $sq->where('role', CentralUser::ROLE_SUBDIVISION_ADMIN)->whereIn('entity_id', $subDivisionIds);
                  });
            })->latest()->get();
        }

        if ($currentUser->isProvinceAdmin()) {
            $subDivisionIds = SubDivision::on('central')->where('province_id', $currentUser->entity_id)->pluck('id');

            return $query->where(function($q) use ($currentUser, $subDivisionIds) {
                $q->where('role', CentralUser::ROLE_PROVINCE_ADMIN)->where('entity_id', $currentUser->entity_id)
                  ->orWhere(function($sq) use ($subDivisionIds) {
                      $sq->where('role', CentralUser::ROLE_SUBDIVISION_ADMIN)->whereIn('entity_id', $subDivisionIds);
                  });
            })->latest()->get();
        }

        if ($currentUser->isSubdivisionAdmin()) {
            return $query->where('role', CentralUser::ROLE_SUBDIVISION_ADMIN)
                         ->where('entity_id', $currentUser->entity_id)
                         ->latest()->get();
        }

        return collect();
    }

    public function openModal()
    {
        $this->resetFields();
        $this->showModal = true;
    }

    public function resetFields()
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = '';
        $this->entity_id = null;
        $this->userId = null;
        $this->isEditing = false;
    }

    public function store()
    {
        $this->validate();

        CentralUser::on('central')->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'entity_id' => $this->entity_id,
        ]);

        session()->flash('success', 'User created successfully.');
        $this->showModal = false;
        $this->resetFields();
    }

    public function edit($id)
    {
        $user = CentralUser::on('central')->findOrFail($id);
        $this->userId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->entity_id = $user->entity_id;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function update()
    {
        $this->validate();

        $user = CentralUser::on('central')->findOrFail($this->userId);
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'entity_id' => $this->entity_id,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        $user->update($data);

        session()->flash('success', 'User updated successfully.');
        $this->showModal = false;
        $this->resetFields();
    }

    public function delete($id)
    {
        $user = CentralUser::on('central')->findOrFail($id);
        
        if (auth('central')->id() === $user->id) {
            session()->flash('error', 'You cannot delete yourself.');
            return;
        }

        $user->delete();
        session()->flash('success', 'User deleted successfully.');
    }
}
