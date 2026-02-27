<?php

namespace App\Services\Task\Dashboard;

use App\Models\Employee\Employee;
use App\Models\Task\Task;
use Illuminate\Http\Request;

class StatService
{
    public function getData(Request $request)
    {
        $employee = Employee::auth()->first();

        $total = Task::query()
            ->selectRaw('count(*) as task')
            ->selectRaw('count(case when start_date >= '."'".today()->startOfMonth()->toDateString()."'".' then 1 end) as task_this_month')
            ->byTeam()
            ->whereHas('members', function ($q) use ($employee) {
                $q->where('employee_id', $employee?->id);
            })
            ->first();

        $completed = Task::query()
            ->selectRaw('count(*) as task')
            ->selectRaw('count(case when start_date >= '."'".today()->startOfMonth()->toDateString()."'".' then 1 end) as task_this_month')
            ->byTeam()
            ->whereHas('members', function ($q) use ($employee) {
                $q->where('employee_id', $employee?->id);
            })
            ->whereNotNull('completed_at')
            ->first();

        $pending = Task::query()
            ->selectRaw('count(*) as task')
            ->selectRaw('count(case when start_date >= '."'".today()->startOfMonth()->toDateString()."'".' then 1 end) as task_this_month')
            ->byTeam()
            ->whereHas('members', function ($q) use ($employee) {
                $q->where('employee_id', $employee?->id);
            })
            ->whereNull('completed_at')
            ->first();

        $overdue = Task::query()
            ->selectRaw('count(*) as task')
            ->selectRaw('count(case when start_date >= '."'".today()->startOfMonth()->toDateString()."'".' then 1 end) as task_this_month')
            ->byTeam()
            ->whereHas('members', function ($q) use ($employee) {
                $q->where('employee_id', $employee?->id);
            })
            ->whereNull('completed_at')
            ->where('due_date', '<', today()->toDateString())
            ->first();

        $stats = [
            [
                'title' => trans('task.dashboard.total_task'),
                'count' => $total->task,
                'icon' => 'fas fa-list-check',
                'color' => 'bg-primary',
                'secondary_title' => trans('global.this_duration', ['attribute' => trans('list.durations.month')]),
                'secondary_count' => $total->task_this_month,
            ],
            [
                'title' => trans('task.dashboard.completed_task'),
                'count' => $completed->task,
                'icon' => 'fas fa-check-circle',
                'color' => 'bg-success',
                'secondary_title' => trans('global.this_duration', ['attribute' => trans('list.durations.month')]),
                'secondary_count' => $completed->task_this_month,
            ],
            [
                'title' => trans('task.dashboard.pending_task'),
                'count' => $pending->task,
                'icon' => 'fas fa-clock',
                'color' => 'bg-info',
                'secondary_title' => trans('global.this_duration', ['attribute' => trans('list.durations.month')]),
                'secondary_count' => $pending->task_this_month,
            ],
            [
                'title' => trans('task.dashboard.overdue_task'),
                'count' => $overdue->task,
                'icon' => 'fas fa-bell',
                'color' => 'bg-danger',
                'secondary_title' => trans('global.this_duration', ['attribute' => trans('list.durations.month')]),
                'secondary_count' => $overdue->task_this_month,
            ],
        ];

        return compact('stats');
    }
}
