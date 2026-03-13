<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\Province;
use App\Models\Central\Ministry;

class ProvinceManager extends Component
{
    public $name, $code, $ministry_id;
    public $provinceId;
    public $message;
    public $isEditing = false;

    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            'code' => 'required|unique:central.provinces,code,' . $this->provinceId,
            'ministry_id' => 'required|exists:central.ministries,id',
        ];
    }

    public function render()
    {
        $currentUser = auth('central')->user();
        
        $query = Province::on('central')->with('ministry');
        $ministryQuery = Ministry::on('central');

        if (!$currentUser) {
            return redirect()->route('central.login');
        }

        if ($currentUser->isPlatformOwner()) {
            // All
        } elseif ($currentUser->isMinistryAdmin()) {
            $query->where('ministry_id', $currentUser->entity_id);
            $ministryQuery->where('id', $currentUser->entity_id);
        } else {
            abort(403, 'Unauthorized access to province management.');
        }

        return view('livewire.central.province-manager', [
            'provinces' => $query->latest()->get(),
            'ministries' => $ministryQuery->get(),
        ])->layout('layouts.central', ['title' => 'Province Management', 'header' => 'Provinces']);
    }

    public function resetFields()
    {
        $this->name = '';
        $this->code = '';
        $this->ministry_id = null;
        $this->provinceId = null;
        $this->isEditing = false;
    }

    public function store()
    {
        $this->validate();

        Province::on('central')->create([
            'name' => $this->name,
            'code' => $this->code,
            'ministry_id' => $this->ministry_id,
        ]);

        app(\App\Services\CentralAuditService::class)->log(
            'province_created', 'Province', null,
            ['name' => $this->name, 'code' => $this->code]
        );

        session()->flash('success', 'Province created successfully.');
        $this->resetFields();
    }

    public function edit($id)
    {
        $province = Province::on('central')->findOrFail($id);
        $this->provinceId = $id;
        $this->name = $province->name;
        $this->code = $province->code;
        $this->ministry_id = $province->ministry_id;
        $this->isEditing = true;
    }

    public function update()
    {
        $this->validate();

        $province = Province::on('central')->findOrFail($this->provinceId);
        $province->update([
            'name' => $this->name,
            'code' => $this->code,
            'ministry_id' => $this->ministry_id,
        ]);

        app(\App\Services\CentralAuditService::class)->log(
            'province_updated', 'Province', $province->id,
            ['name' => $this->name]
        );

        session()->flash('success', 'Province updated successfully.');
        $this->resetFields();
    }

    public function delete($id)
    {
        Province::on('central')->findOrFail($id)->delete();
        session()->flash('success', 'Province deleted successfully.');
    }
}
