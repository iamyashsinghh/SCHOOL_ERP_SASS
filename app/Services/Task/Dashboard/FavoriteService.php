<?php

namespace App\Services\Task\Dashboard;

use App\Helpers\CalHelper;
use App\Models\Employee\Employee;
use App\Models\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class FavoriteService
{
    public function getData(Request $request)
    {
        $employee = Employee::auth()->first();

        $tasks = Task::query()
            ->byTeam()
            ->whereHas('members', function ($q) use ($employee) {
                $q->where('employee_id', $employee?->id)->whereIsFavorite(1);
            })
            ->whereNull('archived_at')
            ->orderBy('due_date', 'desc')
            ->take(5)
            ->get();

        $favorites = [];

        foreach ($tasks as $task) {
            $detail = $this->getDetail($task);

            array_push($favorites, [
                'title' => $task->title,
                'date' => CalHelper::showDate($task->due_date->value),
                'icon' => Arr::get($detail, 'icon'),
                'color' => Arr::get($detail, 'color'),
            ]);
        }

        return $favorites;
    }

    private function getDetail(Task $task): array
    {
        if ($task->completed_at) {
            return ['icon' => 'fas fa-check', 'color' => 'bg-success'];
        } elseif ($task->due_date > today()->toDateString()) {
            return ['icon' => 'fas fa-clock', 'color' => 'bg-info'];
        } elseif ($task->due_date == today()->toDateString()) {
            return ['icon' => 'fas fa-exclamation', 'color' => 'bg-waning'];
        } elseif ($task->due_date < today()->toDateString()) {
            return ['icon' => 'fas fa-times', 'color' => 'bg-danger'];
        }
    }
}
