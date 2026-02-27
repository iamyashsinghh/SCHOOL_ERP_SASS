<?php

namespace App\Services\Task;

use App\Concerns\SubordinateAccess;
use App\Contracts\ListGenerator;
use App\Http\Resources\Task\TaskResource;
use App\Models\Employee\Employee;
use App\Models\Task\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class TaskListService extends ListGenerator
{
    use SubordinateAccess;

    protected $allowedSorts = ['created_at', 'due_date'];

    protected $defaultSort = 'due_date';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'code_number',
                'label' => trans('task.props.code_number'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'title',
                'label' => trans('task.props.title'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'priority',
                'label' => trans('task.priority.priority'),
                'print_label' => 'priority.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('task.category.category'),
                'print_label' => 'category.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'owner',
                'label' => trans('task.props.owner'),
                'print_label' => 'owner.name',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'due',
                'label' => trans('task.props.due_date'),
                'print_label' => 'due.formatted',
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

    public function filter(Request $request): Builder
    {
        $accessibleEmployeeIds = $this->getAccessibleEmployeeIds();
        $employees = Str::toArray($request->query('employees'));
        $employee = Employee::auth()->first();

        $taskCategories = Str::toArray($request->query('categories'));
        $taskPriorities = Str::toArray($request->query('priorities'));
        $tagsIncluded = Str::toArray($request->query('tags_included'));
        $tagsExcluded = Str::toArray($request->query('tags_excluded'));

        return Task::query()
            ->select(
                'tasks.*',
                'task_members.employee_id as member_employee_id',
                'task_members.meta as member_meta',
                'task_members.is_favorite as is_favorite',
                'employees.contact_id as member_contact_id',
                'contacts.user_id as member_user_id'
            )
            ->byTeam()
            ->withMember()
            ->withOwner()
            ->with('priority', 'category', 'list')
            ->when(config('config.task.is_accessible_to_top_level'), function ($q) use ($accessibleEmployeeIds, $employee) {
                $q->where(function ($q) use ($accessibleEmployeeIds, $employee) {
                    $q->whereHas('members', function ($q) use ($accessibleEmployeeIds, $employee) {
                        $q->whereIn('employee_id', $accessibleEmployeeIds)
                            ->orWhere(function ($q) use ($employee) {
                                $q->when(! $employee, function ($q) {
                                    $q->whereNull('employee_id');
                                });
                            });
                    })->orWhereDoesntHave('members');
                });
            }, function ($q) use ($employee) {
                $q->where(function ($q) use ($employee) {
                    $q->whereHas('members', function ($q) use ($employee) {
                        $q->when($employee?->id, function ($q) use ($employee) {
                            $q->where('employee_id', $employee?->id);
                        })
                            ->when(! $employee, function ($q) {
                                $q->whereNull('employee_id');
                            });
                    })->orWhereDoesntHave('members');
                });
            })
            ->when($employees, function ($q, $employees) {
                $q->whereHas('members', function ($q) use ($employees) {
                    $q->whereHas('employee', function ($q) use ($employees) {
                        $q->whereIn('employees.uuid', $employees);
                    });
                });
            })
            ->when($tagsIncluded, function ($q, $tagsIncluded) {
                $q->whereHas('tags', function ($q) use ($tagsIncluded) {
                    $q->whereIn('name', $tagsIncluded);
                });
            })
            ->when($tagsExcluded, function ($q, $tagsExcluded) {
                $q->whereDoesntHave('tags', function ($q) use ($tagsExcluded) {
                    $q->whereIn('name', $tagsExcluded);
                });
            })
            ->when($taskCategories, function ($q, $taskCategories) {
                $q->whereHas('category', function ($q) use ($taskCategories) {
                    $q->whereIn('uuid', $taskCategories);
                });
            })
            ->when($taskPriorities, function ($q, $taskPriorities) {
                $q->whereHas('priority', function ($q) use ($taskPriorities) {
                    $q->whereIn('uuid', $taskPriorities);
                });
            })
            ->when($request->boolean('is_archived'), function ($q) {
                $q->whereNotNull('archived_at');
            }, function ($q) {
                $q->whereNull('archived_at');
            })
            ->filter([
                'App\QueryFilters\LikeMatch:title',
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\ExactMatch:code_number,tasks.code_number',
                'App\QueryFilters\DateBetween:start_date,end_date,start_date',
                'App\QueryFilters\DateBetween:due_start_date,due_end_date,due_date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $view = $request->query('view', 'card');
        $request->merge(['view' => $view]);

        return TaskResource::collection($this->filter($request)
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

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
