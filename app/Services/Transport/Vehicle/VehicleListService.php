<?php

namespace App\Services\Transport\Vehicle;

use App\Contracts\ListGenerator;
use App\Enums\OptionType;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleListService extends ListGenerator
{
    protected $allowedSorts = ['created_at'];

    protected $defaultSort = 'created_at';

    protected $defaultOrder = 'desc';

    public function getHeaders(Request $request): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('transport.vehicle.props.name'),
                'print_label' => 'name',
                'print_sub_label' => 'type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'registrationNumber',
                'label' => trans('transport.vehicle.props.registration_number'),
                'print_label' => 'registration_number',
                'print_sub_label' => 'registration_place',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'registrationDate',
                'label' => trans('transport.vehicle.props.registration_date'),
                'print_label' => 'registration_date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'type',
                'label' => trans('transport.vehicle.props.type'),
                'print_label' => 'type.name',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'modelNumber',
                'label' => trans('transport.vehicle.props.model_number'),
                'print_label' => 'model_number',
                'print_sub_label' => 'model',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'incharge',
                'label' => trans('transport.vehicle.incharge.incharge_short'),
                'print_label' => 'incharges',
                'print_key' => 'employee.name',
                'print_sub_key' => 'period',
                'type' => 'array',
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        foreach ($request->query('document_types') as $documentType) {
            if ($documentType->getMeta('has_number')) {
                $headers[] = [
                    'key' => str_replace('-', '', $documentType->uuid.'Number'),
                    'label' => $documentType->name,
                    'print_label' => str_replace('-', '', $documentType->uuid.'Number'),
                    'sortable' => false,
                    'visibility' => false,
                ];
            }

            if ($documentType->getMeta('has_expiry_date')) {
                $headers[] = [
                    'key' => str_replace('-', '', $documentType->uuid.'EndDate'),
                    'label' => $documentType->name.' '.trans('transport.vehicle.document.props.end_date'),
                    'print_label' => str_replace('-', '', $documentType->uuid.'EndDate.label'),
                    'sortable' => false,
                    'visibility' => false,
                ];
            }
        }

        $headers[] = [
            'key' => 'createdAt',
            'label' => trans('general.created_at'),
            'print_label' => 'created_at.formatted',
            'sortable' => true,
            'visibility' => true,
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $details = $request->query('details');
        $registrationNumber = $request->query('registration_number');

        $documentTypes = $request->document_types;
        $documentType = $request->query('document_type');
        $documentTypeId = $documentTypes->where('uuid', $documentType)->first()?->id;
        $documentNumber = $request->query('document_number');

        return Vehicle::query()
            ->with('type')
            ->byTeam()
            ->with(['documents' => function ($q) use ($documentTypes) {
                $q->whereIn('type_id', $documentTypes->pluck('id'));
            }])
            ->when($documentTypeId, function ($q) use ($documentTypeId, $documentNumber) {
                $q->whereHas('documents', function ($q) use ($documentTypeId, $documentNumber) {
                    $q->where('type_id', $documentTypeId)
                        ->where('number', 'like', "%{$documentNumber}%");
                });
            })
            ->when($details, function ($q) {
                $q->withCurrentIncharges();
            })
            ->when($registrationNumber, function ($q, $registrationNumber) {
                $q->where('registration->number', 'like', "%{$registrationNumber}%");
            })
            ->filter([
                'App\QueryFilters\LikeMatch:model_number',
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $documentTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_DOCUMENT_TYPE)
            ->where(function ($q) {
                $q->where('meta->has_number', true)
                    ->orWhere('meta->has_expiry_date', true);
            })
            ->get();

        $request->merge(['document_types' => $documentTypes]);

        return VehicleResource::collection($this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders($request),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                    'document_types' => $documentTypes->map(function ($documentType) {
                        return [
                            'uuid' => str_replace('-', '', $documentType->uuid),
                            'name' => $documentType->name,
                            'has_number' => (bool) $documentType->getMeta('has_number'),
                            'has_expiry_date' => (bool) $documentType->getMeta('has_expiry_date'),
                        ];
                    }),
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
