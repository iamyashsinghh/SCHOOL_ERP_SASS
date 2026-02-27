<?php

namespace App\Services\Finance;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\DayClosureResource;
use App\Models\Employee\Employee;
use App\Models\Finance\DayClosure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class DayClosureListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'date', 'total'];

    protected $defaultSort = 'date';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'user',
                'label' => trans('user.user'),
                'print_label' => 'user.profile.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'date',
                'label' => trans('finance.day_closure.props.date'),
                'type' => 'date',
                'print_label' => 'date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'total',
                'label' => trans('finance.day_closure.props.total'),
                'type' => 'currency',
                'print_label' => 'total.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'denominations',
                'label' => trans('finance.day_closure.props.denominations'),
                'type' => 'array',
                'print_label' => 'denominations',
                'print_key' => 'label',
                'print_sub_key' => 'total.formatted',
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
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $employees = Str::toArray($request->query('employees'));

        $employees = Employee::query()
            ->select('employees.id', 'contacts.user_id')
            ->leftJoin('contacts', 'employees.contact_id', '=', 'contacts.id')
            ->whereIn('employees.uuid', $employees)
            ->get();

        $employeeUserIds = $employees->pluck('user_id')->filter()->toArray();

        return DayClosure::query()
            ->with('user')
            ->where('team_id', auth()->user()->current_team_id)
            ->when($employeeUserIds, function ($q, $employeeUserIds) {
                return $q->whereIn('day_closures.user_id', $employeeUserIds);
            })
            ->when(auth()->user()->can('day-closure:manage'), function ($q) {
                return $q->whereNotNull('day_closures.id');
            }, function ($q) {
                return $q->where('day_closures.user_id', auth()->id());
            })
            ->filter([
                'App\QueryFilters\DateBetween:start_date,end_date,date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        return DayClosureResource::collection($records)
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'filename' => 'Day Closure Report',
                    'sno' => $this->getSno(),
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                    'has_footer' => false,
                ],
                'footers' => [],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
