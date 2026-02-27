<?php

namespace App\Services\Employee;

use App\Concerns\SubordinateAccess;
use App\Contracts\ListGenerator;
use App\Http\Resources\Employee\QualificationsResource;
use App\Models\Contact;
use App\Models\Employee\Employee;
use App\Models\Qualification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class QualificationsListService extends ListGenerator
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
                'key' => 'course',
                'label' => trans('employee.qualification.props.course'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'institute',
                'label' => trans('employee.qualification.props.institute'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'level',
                'label' => trans('contact.qualification_level.qualification_level'),
                'print_label' => 'level.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'period',
                'label' => trans('general.period'),
                'print_label' => 'period',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'result',
                'label' => trans('employee.qualification.props.result'),
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

        return Qualification::query()
            ->with(['model', 'level'])
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
                'App\QueryFilters\ExactMatch:course',
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

        return QualificationsResource::collection($records)
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
