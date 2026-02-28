<?php

namespace App\Services\Employee;

use App\Concerns\SubordinateAccess;
use App\Contracts\ListGenerator;
use App\Enums\OptionType;
use App\Http\Resources\Employee\DocumentsResource;
use App\Http\Resources\Employee\DocumentSummaryResource;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Document;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Option;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class DocumentsListService extends ListGenerator
{
    use SubordinateAccess;

    protected $allowedSorts = ['created_at', 'issue_date', 'start_date', 'end_date', 'expiry_in_days'];

    protected $defaultSort = 'created_at';

    protected $defaultOrder = 'desc';

    public function getHeaders(Request $request): array
    {
        if ($request->query('report_type') == 'summary') {
            return $this->getSummaryHeaders($request);
        }

        $headers = [
            [
                'key' => 'employee',
                'label' => trans('employee.employee'),
                'print_label' => 'employee.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'codeNumber',
                'label' => trans('employee.props.code_number'),
                'print_label' => 'employee.code_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'designation',
                'label' => trans('employee.designation.designation'),
                'print_label' => 'employee.designation',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'documentType',
                'label' => trans('employee.document_type.document_type'),
                'print_label' => 'type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'title',
                'label' => trans('employee.document.props.title'),
                'print_label' => 'title',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'number',
                'label' => trans('employee.document.props.number'),
                'print_label' => 'number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'status',
                'label' => trans('employee.verification.props.status'),
                'print_label' => 'verification_status.label',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'submittedOriginal',
                'label' => trans('employee.document.props.is_submitted_original'),
                'print_label' => 'is_submitted_original',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'startDate',
                'label' => trans('employee.document.props.start_date'),
                'print_label' => 'start_date.formatted',
                'print_sub_label' => 'issue_date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'endDate',
                'label' => trans('employee.document.props.end_date'),
                'print_label' => 'end_date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'expiryInDays',
                'label' => trans('employee.document.props.expiry_in_days'),
                'print_label' => 'expiry_in_days',
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

    private function getSummaryHeaders(Request $request): array
    {
        $headers = [
            [
                'key' => 'employee',
                'label' => trans('employee.employee'),
                'print_label' => 'name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'codeNumber',
                'label' => trans('employee.props.code_number'),
                'print_label' => 'code_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'designation',
                'label' => trans('employee.designation.designation'),
                'print_label' => 'designation',
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        foreach ($request->document_types as $documentType) {
            $headers[] = [
                'key' => str_replace('-', '_', $documentType->uuid),
                'label' => $documentType->name,
                'type' => 'array',
                'print_label' => 'documents.'.str_replace('-', '_', $documentType->uuid).'.status.label',
                'sortable' => false,
                'visibility' => true,
            ];
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $accessibleEmployees = $this->getAccessibleEmployees();
        $accessibleEmployeeContactIds = $accessibleEmployees->pluck('contact_id')->all();

        $employees = Str::toArray($request->query('employees'));
        $filteredEmployeeContactIds = [];
        if ($employees) {
            $filteredEmployeeContactIds = $accessibleEmployees->whereIn('uuid', $employees)->pluck('contact_id')->all();
        }

        $documentTypes = Str::toArray($request->query('types'));
        $expiryInDays = $request->query('expiry_in_days');
        $expiryStatus = $request->query('expiry_status');

        $status = $request->query('status');

        return Document::query()
            ->with(['documentable', 'type'])
            ->select('documents.*')
            ->selectRaw('DATEDIFF(end_date, CURDATE()) as expiry_in_days')
            ->whereHasMorph(
                'documentable', [Contact::class],
                function ($q) use ($accessibleEmployeeContactIds, $filteredEmployeeContactIds) {
                    $q->whereIn('id', $accessibleEmployeeContactIds)->when($filteredEmployeeContactIds, function ($q) use ($filteredEmployeeContactIds) {
                        $q->whereIn('id', $filteredEmployeeContactIds);
                    });
                }
            )
            ->when($documentTypes, function ($q, $documentTypes) {
                $q->whereHas('type', function ($q) use ($documentTypes) {
                    $q->whereIn('uuid', $documentTypes);
                });
            })
            ->when($expiryInDays, function ($q, $expiryInDays) {
                $q->havingRaw('DATEDIFF(end_date, CURDATE()) <= ?', [$expiryInDays]);
            })
            ->when($expiryStatus == 'expired', function ($q) {
                $q->whereNotNull('end_date')->where('end_date', '<', today()->toDateString());
            })
            ->when($expiryStatus == 'expiring_soon', function ($q) {
                $q->whereNotNull('end_date')->where('end_date', '>=', today()->toDateString())
                    ->where('end_date', '<', today()->addWeeks(1)->toDateString());
            })
            ->when($expiryStatus == 'valid', function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString());
                });
            })
            ->when($status == 'approved', function ($q) {
                $q->whereNotNull('verified_at');
            })
            ->when($status == 'pending', function ($q) {
                $q->whereNull('verified_at')->where('meta->self_upload', true);
            })
            ->when($status == 'rejected', function ($q) {
                $q->whereNull('verified_at')->where('meta->self_upload', true)->where('meta->status', 'rejected');
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\ExactMatch:number',
                'App\QueryFilters\DateBetween:issue_start_date,issue_end_date,issue_date',
                'App\QueryFilters\DateBetween:start_start_date,start_end_date,start_date',
                'App\QueryFilters\DateBetween:end_start_date,end_end_date,end_date',
            ]);
    }

    private function getSummaryFilter(Request $request): Builder
    {
        $accessibleEmployees = $this->getAccessibleEmployees();
        $accessibleEmployeeContactIds = $accessibleEmployees->pluck('contact_id')->all();

        $employees = Str::toArray($request->query('employees'));
        $filteredEmployeeContactIds = [];
        if ($employees) {
            $filteredEmployeeContactIds = $accessibleEmployees->whereIn('uuid', $employees)->pluck('contact_id')->all();
        }

        return Employee::query()
            ->summary()
            ->whereIn('contact_id', $accessibleEmployeeContactIds)
            ->when($filteredEmployeeContactIds, function ($q) use ($filteredEmployeeContactIds) {
                $q->whereIn('contact_id', $filteredEmployeeContactIds);
            });
    }

    public function paginate(Request $request): array|AnonymousResourceCollection
    {
        if ($request->query('report_type') == 'summary') {
            $types = Option::query()
                ->byTeam()
                ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
                ->when($request->query('types'), function ($q, $types) {
                    $q->whereIn('uuid', $types);
                })
                ->orderBy('name', 'asc')
                ->get();

            $records = $this->getSummaryFilter($request)
                ->orderBy('name', 'asc')
                ->when($request->query('output') == 'export_all_excel', function ($q) {
                    return $q->get();
                }, function ($q) {
                    return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
                });

            $documents = Document::query()
                ->where('documentable_type', 'Contact')
                ->whereIn('documentable_id', $records->pluck('contact_id')->all())
                ->get();

            $request->merge([
                'documents' => $documents,
                'document_types' => $types,
            ]);

            return DocumentSummaryResource::collection($records)
                ->additional([
                    'headers' => $this->getSummaryHeaders($request),
                    'meta' => [
                        'allowed_sorts' => ['code_number'],
                        'default_sort' => 'code_number',
                        'default_order' => 'asc',
                    ],
                ]);
        }

        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->when($request->query('output') == 'export_all_excel', function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            });

        $contactIds = $records->pluck('documentable_id')->unique()->all();

        $employees = Employee::query()
            ->summary()
            ->whereIn('contact_id', $contactIds)
            ->get();

        $request->merge([
            'employees' => $employees,
        ]);

        return DocumentsResource::collection($records)
            ->additional([
                'headers' => $this->getHeaders($request),
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
