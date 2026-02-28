<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\Ministry;

class MinistryManager extends Component
{
    public $name, $code, $status = 'active';
    public $ministryId;
    public $isEditing = false;

    protected $rules = [
        'name' => 'required|min:3',
        'code' => 'required|unique:central.ministries,code',
        'status' => 'required|in:active,suspended',
    ];

    public function render()
    {
        $currentUser = auth('central')->user();
        
        // Only PO and Ministry Admin can see ministries
        if ($currentUser->isProvinceAdmin() || $currentUser->isSubdivisionAdmin()) {
            abort(403, 'Unauthorized access to ministry management.');
        }

        $query = Ministry::on('central');

        if ($currentUser->isMinistryAdmin()) {
            $query->where('id', $currentUser->entity_id);
        }

        return view('livewire.central.ministry-manager', [
            'ministries' => $query->latest()->get()
        ])->layout('layouts.central', ['title' => 'Ministry Management', 'header' => 'Ministries']);
    }

    public function resetFields()
    {
        $this->name = '';
        $this->code = '';
        $this->status = 'active';
        $this->ministryId = null;
        $this->isEditing = false;
    }

    public function store()
    {
        if (!auth('central')->user()->isPlatformOwner()) {
            abort(403, 'Only Platform Owners can create ministries.');
        }

        $this->validate();

        $ministry = Ministry::on('central')->create([
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
        ]);

        app(\App\Services\CentralAuditService::class)->log(
            'ministry_created',
            'Ministry',
            $ministry->id,
            ['name' => $this->name, 'code' => $this->code]
        );

        session()->flash('success', 'Ministry created successfully.');
        $this->resetFields();
    }

    public function edit($id)
    {
        $user = auth('central')->user();
        if (!$user->isPlatformOwner() && !($user->isMinistryAdmin() && $user->entity_id == $id)) {
            abort(403, 'Unauthorized access to edit this ministry.');
        }

        $ministry = Ministry::on('central')->findOrFail($id);
        $this->ministryId = $id;
        $this->name = $ministry->name;
        $this->code = $ministry->code;
        $this->status = $ministry->status;
        $this->isEditing = true;
    }

    public function update()
    {
        $user = auth('central')->user();
        if (!$user->isPlatformOwner() && !($user->isMinistryAdmin() && $user->entity_id == $this->ministryId)) {
            abort(403, 'Unauthorized access to update this ministry.');
        }

        $this->validate([
            'name' => 'required|min:3',
            'code' => 'required|unique:central.ministries,code,' . $this->ministryId,
            'status' => 'required|in:active,suspended',
        ]);

        $ministry = Ministry::on('central')->findOrFail($this->ministryId);
        $ministry->update([
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
        ]);

        app(\App\Services\CentralAuditService::class)->log(
            'ministry_updated',
            'Ministry',
            $ministry->id,
            ['name' => $this->name, 'code' => $this->code]
        );

        session()->flash('success', 'Ministry updated successfully.');
        $this->resetFields();
    }

    public function toggleStatus($id)
    {
        if (!auth('central')->user()->isPlatformOwner()) {
            abort(403, 'Only Platform Owners can change ministry status.');
        }

        $ministry = Ministry::on('central')->findOrFail($id);
        $ministry->status = $ministry->status === 'active' ? 'suspended' : 'active';
        $ministry->save();

        app(\App\Services\CentralAuditService::class)->log(
            'ministry_status_toggled',
            'Ministry',
            $ministry->id,
            ['new_status' => $ministry->status]
        );
    }
}
