<?php

namespace App\Services\Helpdesk\Ticket;

use App\Concerns\SubordinateAccess;
use App\Contracts\ListGenerator;
use App\Http\Resources\Helpdesk\Ticket\TicketListResource;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Helpdesk\Ticket\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class TicketListService extends ListGenerator
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
                'label' => trans('helpdesk.ticket.props.code_number'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'title',
                'label' => trans('helpdesk.ticket.props.title'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'priority',
                'label' => trans('helpdesk.ticket.priority.priority'),
                'print_label' => 'priority.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('helpdesk.ticket.category.category'),
                'print_label' => 'category.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'employee',
                'label' => trans('employee.employee'),
                'print_label' => 'employee.name',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'status',
                'label' => trans('helpdesk.ticket.props.status'),
                'print_label' => 'status.label',
                'sortable' => false,
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
            if (auth()->user()->can('ticket:edit')) {
                array_unshift($headers, ['key' => 'selectAll', 'sortable' => false]);
            }
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        // $accessibleEmployeeIds = $this->getAccessibleEmployeeIds();
        // $employees = Str::toArray($request->query('employees'));
        // $employee = Employee::auth()->first();

        $ticketCategories = Str::toArray($request->query('categories'));
        $ticketPriorities = Str::toArray($request->query('priorities'));
        $tagsIncluded = Str::toArray($request->query('tags_included'));
        $tagsExcluded = Str::toArray($request->query('tags_excluded'));

        return Ticket::query()
            ->byTeam()
            ->with('priority', 'category', 'list')
            ->filterAccessible()
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
            ->when($ticketCategories, function ($q, $ticketCategories) {
                $q->whereHas('category', function ($q) use ($ticketCategories) {
                    $q->whereIn('uuid', $ticketCategories);
                });
            })
            ->when($ticketPriorities, function ($q, $ticketPriorities) {
                $q->whereHas('priority', function ($q) use ($ticketPriorities) {
                    $q->whereIn('uuid', $ticketPriorities);
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
                'App\QueryFilters\ExactMatch:code_number,tickets.code_number',
                'App\QueryFilters\DateBetween:start_date,end_date,created_at,datetime',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        $userIds = $records->pluck('user_id')->unique()->toArray();

        $employees = Employee::query()
            ->summary()
            ->whereIn('user_id', $userIds)
            ->get();

        $request->merge([
            'employees' => $employees,
        ]);

        return TicketListResource::collection($records)
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

    public function getIds(Request $request): array
    {
        return $this->filter($request)->select('uuid')->get()->pluck('uuid')->all();
    }
}
