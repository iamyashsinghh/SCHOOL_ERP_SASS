<?php

namespace App\Livewire\Central;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Central\CentralAudit;

class AuditViewer extends Component
{
    use WithPagination;

    public $search = '';
    public $filterAction = '';
    public $message;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = CentralAudit::on('central')->latest();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('action', 'like', "%{$this->search}%")
                  ->orWhere('entity_type', 'like', "%{$this->search}%")
                  ->orWhere('metadata', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterAction) {
            $query->where('action', $this->filterAction);
        }

        return view('livewire.central.audit-viewer', [
            'logs' => $query->paginate(20),
            'actions' => CentralAudit::on('central')->distinct()->pluck('action'),
        ])->layout('layouts.central', ['title' => 'Audit Logs', 'header' => 'Audit Logs']);
    }
}
