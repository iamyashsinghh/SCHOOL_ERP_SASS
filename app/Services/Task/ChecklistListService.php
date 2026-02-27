<?php

namespace App\Services\Task;

use App\Contracts\ListGenerator;
use App\Http\Resources\Task\ChecklistResource;
use App\Models\Task\Checklist;
use App\Models\Task\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChecklistListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'title'];

    protected $defaultSort = 'title';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'title',
                'label' => trans('task.checklist.props.title'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'status',
                'label' => '',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'due',
                'label' => trans('task.checklist.props.due'),
                'print_label' => 'due',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'completedAt',
                'label' => trans('task.checklist.props.completed_at'),
                'print_label' => 'completed_at_display',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'createdAt',
                'label' => trans('general.created_at'),
                'print_label' => 'created_at.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request, Task $task): Builder
    {
        return Checklist::query()
            ->whereTaskId($task->id)
            ->filter([
                'App\QueryFilters\LikeMatch:title',
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\DateBetween:start_date,end_date,due_date',
            ]);
    }

    public function paginate(Request $request, Task $task): AnonymousResourceCollection
    {
        return ChecklistResource::collection($this->filter($request, $task)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                ],
            ]);
    }

    public function list(Request $request, Task $task): AnonymousResourceCollection
    {
        return $this->paginate($request, $task);
    }
}
