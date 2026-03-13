<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\School;
use App\Models\Central\Ministry;
use App\Models\Central\Province;
use App\Models\Central\SubDivision;

class Dashboard extends Component
{
    public $totalSchools = 0;
    public $activeSchools = 0;
    public $suspendedSchools = 0;
    public $totalMinistries = 0;
    public $totalProvinces = 0;
    public $totalSubDivisions = 0;
    public $recentSchools = [];

    public function mount()
    {
        $currentUser = auth('central')->user();

        if (!$currentUser) {
            return redirect()->route('central.login');
        }

        $schoolQuery = School::on('central');
        $ministryQuery = Ministry::on('central');
        $provinceQuery = Province::on('central');
        $subDivisionQuery = SubDivision::on('central');

        // Scope by user role
        if ($currentUser->isPlatformOwner()) {
            // No filter — see everything
        } elseif ($currentUser->isMinistryAdmin()) {
            $schoolQuery->whereHas('subDivision.province', function($q) use ($currentUser) {
                $q->where('ministry_id', $currentUser->entity_id);
            });
            $ministryQuery->where('id', $currentUser->entity_id);
            $provinceQuery->where('ministry_id', $currentUser->entity_id);
            $subDivisionQuery->whereHas('province', function($q) use ($currentUser) {
                $q->where('ministry_id', $currentUser->entity_id);
            });
        } elseif ($currentUser->isProvinceAdmin()) {
            $schoolQuery->whereHas('subDivision', function($q) use ($currentUser) {
                $q->where('province_id', $currentUser->entity_id);
            });
            $provinceQuery->where('id', $currentUser->entity_id);
            $subDivisionQuery->where('province_id', $currentUser->entity_id);
        } elseif ($currentUser->isSubdivisionAdmin()) {
            $schoolQuery->where('sub_division_id', $currentUser->entity_id);
            $subDivisionQuery->where('id', $currentUser->entity_id);
        }

        $this->totalSchools = (clone $schoolQuery)->count();
        $this->activeSchools = (clone $schoolQuery)->where('status', 'active')->count();
        $this->suspendedSchools = (clone $schoolQuery)->where('status', 'suspended')->count();
        $this->totalMinistries = $ministryQuery->count();
        $this->totalProvinces = $provinceQuery->count();
        $this->totalSubDivisions = $subDivisionQuery->count();
        $this->recentSchools = $schoolQuery->with('subDivision', 'domains')->latest()->limit(10)->get();
    }

    public function render()
    {
        return view('livewire.central.dashboard')
            ->layout('layouts.central', ['title' => 'Dashboard', 'header' => 'Dashboard']);
    }
}
