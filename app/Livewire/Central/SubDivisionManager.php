<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\SubDivision;
use App\Models\Central\Province;

class SubDivisionManager extends Component
{
    public $name, $code, $province_id;
    public $subDivisionId;
    public $message;
    public $isEditing = false;

    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            'code' => 'required|unique:central.sub_divisions,code,' . $this->subDivisionId,
            'province_id' => 'required|exists:central.provinces,id',
        ];
    }

    public function render()
    {
        $currentUser = auth('central')->user();
        
        $query = SubDivision::on('central')->with('province.ministry');
        $provinceQuery = Province::on('central')->with('ministry');

        if (!$currentUser) {
            return redirect()->route('central.login');
        }

        if ($currentUser->isPlatformOwner()) {
            // All
        } elseif ($currentUser->isMinistryAdmin()) {
            $query->whereHas('province', function($q) use ($currentUser) {
                $q->where('ministry_id', $currentUser->entity_id);
            });
            $provinceQuery->where('ministry_id', $currentUser->entity_id);
        } elseif ($currentUser->isProvinceAdmin()) {
            $query->where('province_id', $currentUser->entity_id);
            $provinceQuery->where('id', $currentUser->entity_id);
        } else {
            abort(403, 'Unauthorized access.');
        }

        return view('livewire.central.sub-division-manager', [
            'subDivisions' => $query->latest()->get(),
            'provinces' => $provinceQuery->get(),
        ])->layout('layouts.central', ['title' => 'Sub-Division Management', 'header' => 'Sub-Divisions']);
    }

    public function resetFields()
    {
        $this->name = '';
        $this->code = '';
        $this->province_id = null;
        $this->subDivisionId = null;
        $this->isEditing = false;
    }

    public function store()
    {
        $this->validate();

        SubDivision::on('central')->create([
            'name' => $this->name,
            'code' => $this->code,
            'province_id' => $this->province_id,
        ]);

        app(\App\Services\CentralAuditService::class)->log(
            'subdivision_created', 'SubDivision', null,
            ['name' => $this->name, 'code' => $this->code]
        );

        session()->flash('success', 'Sub-Division created successfully.');
        $this->resetFields();
    }

    public function edit($id)
    {
        $sd = SubDivision::on('central')->findOrFail($id);
        $this->subDivisionId = $id;
        $this->name = $sd->name;
        $this->code = $sd->code;
        $this->province_id = $sd->province_id;
        $this->isEditing = true;
    }

    public function update()
    {
        $this->validate();

        $sd = SubDivision::on('central')->findOrFail($this->subDivisionId);
        $sd->update([
            'name' => $this->name,
            'code' => $this->code,
            'province_id' => $this->province_id,
        ]);

        app(\App\Services\CentralAuditService::class)->log(
            'subdivision_updated', 'SubDivision', $sd->id,
            ['name' => $this->name]
        );

        session()->flash('success', 'Sub-Division updated successfully.');
        $this->resetFields();
    }

    public function delete($id)
    {
        SubDivision::on('central')->findOrFail($id)->delete();
        session()->flash('success', 'Sub-Division deleted successfully.');
    }
}
