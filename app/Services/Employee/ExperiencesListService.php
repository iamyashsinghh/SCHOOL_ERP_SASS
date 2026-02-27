<?php

namespace App\Services\Employee;

use App\Concerns\SubordinateAccess;
use App\Contracts\ListGenerator;
use App\Http\Resources\Employee\ExperiencesResource;
use App\Models\Contact;
use App\Models\Employee\Employee;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ExperiencesListService extends ListGenerator
{
    use SubordinateAccess;

    protected $allowedSorts = ['created_at'];

    protected $defaultSort = 'created_at';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
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
                'key' => 'headline',
                'label' => trans('employee.experience.props.headline'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'title',
                'label' => trans('employee.experience.props.title'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'organizationName',
                'label' => trans('employee.experience.props.organization_name'),
                'print_label' => 'organization_name',
                'print_sub_label' => 'duration',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'location',
                'label' => trans('employee.experience.props.location'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'employmentType',
                'label' => trans('employee.employment_type.employment_type'),
                'print_label' => 'employment_type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'startDate',
                'label' => trans('employee.experience.props.start_date'),
                'print_label' => 'start_date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'endDate',
                'label' => trans('employee.experience.props.end_date'),
                'print_label' => 'end_date.formatted',
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
        $accessibleEmployees = $this->getAccessibleEmployees();
        $accessibleEmployeeContactIds = $accessibleEmployees->pluck('contact_id')->all();

        $employees = Str::toArray($request->query('employees'));
        $filteredEmployeeContactIds = [];
        if ($employees) {
            $filteredEmployeeContactIds = $accessibleEmployees->whereIn('uuid', $employees)->pluck('contact_id')->all();
        }

        $status = $request->query('status');

        return Experience::query()
            ->with(['model', 'employmentType'])
            ->whereHasMorph(
                'model', [Contact::class],
                function ($q) use ($accessibleEmployeeContactIds, $filteredEmployeeContactIds) {
                    $q->whereIn('id', $accessibleEmployeeContactIds)->when($filteredEmployeeContactIds, function ($q) use ($filteredEmployeeContactIds) {
                        $q->whereIn('id', $filteredEmployeeContactIds);
                    });
                }
            )
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
                'App\QueryFilters\ExactMatch:headline',
                'App\QueryFilters\ExactMatch:location',
                'App\QueryFilters\DateBetween:start_date,end_date,start_date,end_date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->when($request->query('output') == 'export_all_excel', function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            });

        $contactIds = $records->pluck('model_id')->unique()->all();

        $employees = Employee::query()
            ->summary()
            ->whereIn('contact_id', $contactIds)
            ->get();

        $request->merge([
            'employees' => $employees,
        ]);

        return ExperiencesResource::collection($records)
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
