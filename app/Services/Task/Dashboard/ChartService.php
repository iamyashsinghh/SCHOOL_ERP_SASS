<?php

namespace App\Services\Task\Dashboard;

use App\Enums\OptionType;
use App\Helpers\ListHelper;
use App\Models\Employee\Employee;
use App\Models\Option;
use App\Models\Task\Task;
use Illuminate\Http\Request;

class ChartService
{
    public function getData(Request $request): array
    {
        $colors = ListHelper::getListKey('colors');
        $employee = Employee::auth()->first();
        $categories = Option::whereType(OptionType::TASK_CATEGORY->value)->get();
        $priorities = Option::whereType(OptionType::TASK_PRIORITY->value)->get();

        $tasks = Task::query()
            ->byTeam()
            ->selectRaw('category_id, count(*) as count')
            ->groupBy('category_id')
            ->whereHas('members', function ($query) use ($employee) {
                $query->where('employee_id', $employee?->id);
            })
            ->get();

        $labels = [];
        $data = [];
        $color = [];
        foreach ($categories as $category) {
            array_push($labels, $category->name);
            array_push($data, $tasks->firstWhere('category_id', $category->id)?->count ?? 0);
            array_push($color, $category->getMeta('color') ?? array_shift($colors));
        }

        $categoryWiseData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $color,
                ],
            ],
        ];

        $tasks = Task::query()
            ->byTeam()
            ->selectRaw('priority_id, count(*) as count')
            ->groupBy('priority_id')
            ->whereHas('members', function ($query) use ($employee) {
                $query->where('employee_id', $employee?->id);
            })
            ->get();

        $labels = [];
        $data = [];
        $color = [];
        foreach ($priorities as $priority) {
            array_push($labels, $priority->name);
            array_push($data, $tasks->firstWhere('priority_id', $priority->id)?->count ?? 0);
            array_push($color, $priority->getMeta('color') ?? array_shift($colors));
        }

        $priorityWiseData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $color,
                ],
            ],
        ];

        return compact('categoryWiseData', 'priorityWiseData');
    }
}
