<?php

namespace App\Livewire\Central;

use Livewire\Component;
use App\Models\Central\School;
use App\Models\Central\Ministry;

class Dashboard extends Component
{
    public function render()
    {
        $currentUser = auth('central')->user();
        $schoolQuery = School::on('central');
        $ministryQuery = Ministry::on('central');

        if ($currentUser->isPlatformOwner()) {
            // All
        } elseif ($currentUser->isMinistryAdmin()) {
            $schoolQuery->whereHas('subDivision.province', function($q) use ($currentUser) {
                $q->where('ministry_id', $currentUser->entity_id);
            });
            $ministryQuery->where('id', $currentUser->entity_id);
        } elseif ($currentUser->isProvinceAdmin()) {
            $schoolQuery->whereHas('subDivision', function($q) use ($currentUser) {
                $q->where('province_id', $currentUser->entity_id);
            });
            $ministryQuery->whereRaw('1=0'); // No ministries for province admin
        } elseif ($currentUser->isSubdivisionAdmin()) {
            $schoolQuery->where('sub_division_id', $currentUser->entity_id);
            $ministryQuery->whereRaw('1=0');
        }

        return view('livewire.central.dashboard', [
            'schoolsCount' => $schoolQuery->count(),
            'ministriesCount' => $ministryQuery->count(),
        ])->layout('layouts.central', ['title' => 'Governance Dashboard', 'header' => 'Dashboard']);
    }
}
