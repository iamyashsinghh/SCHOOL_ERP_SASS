<?php

namespace App\Services\Task\Dashboard;

use App\Http\Resources\Task\TaskResource;
use App\Models\Employee\Employee;
use App\Models\Task\Task;
use Illuminate\Http\Request;

class RecordService
{
    public function getData(Request $request): array
    {
        $types = [
            ['label' => trans('general.recent'), 'value' => 'recent'],
            ['label' => trans('task.props.owned'), 'value' => 'owned'],
            ['label' => trans('task.statuses.completed'), 'value' => 'completed'],
            ['label' => trans('task.statuses.pending'), 'value' => 'pending'],
            ['label' => trans('task.statuses.overdue'), 'value' => 'overdue'],
        ];

        $type = $request->type ?: 'recent';

        $typeLabel = match ($type) {
            'owned' => trans('task.props.owned'),
            'completed' => trans('task.statuses.completed'),
            'pending' => trans('task.statuses.pending'),
            'overdue' => trans('task.statuses.overdue'),
            default => trans('general.recent'),
        };

        $label = trans('global.list_type', ['type' => $typeLabel, 'attribute' => trans('task.task')]);

        $employee = Employee::auth()->first();

        $tasks = TaskResource::collection(Task::query()
            ->byTeam()
            ->withOwner()
            ->with('priority', 'category')
            ->whereHas('members', function ($q) use ($employee, $type) {
                $q->where('employee_id', $employee?->id)
                    ->when($type == 'owned', function ($q) {
                        $q->where('is_owner', 1);
                    });
            })
            ->when($type == 'recent', function ($q) {
                $q->orderBy('created_at', 'desc');
            })
            ->when($type == 'completed', function ($q) {
                $q->whereNotNull('completed_at')
                    ->orderBy('completed_at', 'desc');
            })
            ->when($type == 'pending', function ($q) {
                $q->whereNull('completed_at')
                    ->where('due_date', '>=', today())
                    ->orderBy('due_date', 'desc');
            })
            ->when($type == 'overdue', function ($q) {
                $q->whereNull('completed_at')
                    ->where('due_date', '<', today())
                    ->orderBy('due_date', 'desc');
            })
            ->take(5)
            ->get());

        return compact('tasks', 'types', 'label');
    }
}
