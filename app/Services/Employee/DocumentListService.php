<?php

namespace App\Services\Employee;

use App\Contracts\ListGenerator;
use App\Http\Resources\Employee\DocumentResource;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Document;
use App\Models\Tenant\Employee\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentListService extends ListGenerator
{
    protected $allowedSorts = ['created_at'];

    protected $defaultSort = 'created_at';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'title',
                'label' => trans('employee.document.props.title'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'number',
                'label' => trans('employee.document.props.number'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'type',
                'label' => trans('employee.document_type.document_type'),
                'print_label' => 'type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'startDate',
                'label' => trans('employee.document.props.start_date'),
                'print_label' => 'start_date.formatted',
                'print_sub_label' => 'issue_date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'endDate',
                'label' => trans('employee.document.props.end_date'),
                'print_label' => 'end_date.formatted',
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

    public function filter(Request $request, Employee $employee): Builder
    {
        return Document::query()
            ->with('type')
            ->whereHasMorph(
                'documentable', [Contact::class],
                function ($q) use ($employee) {
                    $q->whereId($employee->contact_id);
                }
            )->filter([
                'App\QueryFilters\LikeMatch:title',
                'App\QueryFilters\ExactMatch:number',
                'App\QueryFilters\DateBetween:issue_start_date,issue_end_date,issue_date',
                'App\QueryFilters\DateBetween:start_start_date,start_end_date,start_date',
                'App\QueryFilters\DateBetween:end_start_date,end_end_date,end_date',
            ]);
    }

    public function paginate(Request $request, Employee $employee): AnonymousResourceCollection
    {
        return DocumentResource::collection($this->filter($request, $employee)
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

    public function list(Request $request, Employee $employee): AnonymousResourceCollection
    {
        return $this->paginate($request, $employee);
    }
}
