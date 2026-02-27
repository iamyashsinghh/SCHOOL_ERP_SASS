<?php

namespace App\Services\Transport;

use App\Contracts\ListGenerator;
use App\Enums\OptionType;
use App\Http\Resources\Transport\RouteListResource;
use App\Models\Option;
use App\Models\Transport\Route;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class RouteListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'name', 'maxCapacity'];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'asc';

    public function getHeaders(Request $request): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('transport.route.props.name'),
                'print_label' => 'name',
                'print_sub_label' => 'direction.label',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'vehicle',
                'label' => trans('transport.vehicle.vehicle'),
                'print_label' => 'vehicle.registration_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'time',
                'label' => trans('general.time'),
                'print_label' => 'arrival_starts_at.formatted',
                'print_sub_label' => 'departure_starts_at.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'maxCapacity',
                'label' => trans('transport.route.props.max_capacity'),
                'print_label' => 'max_capacity',
                'print_sub_label' => 'route_passengers_count',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'routeStoppagesCount',
                'label' => trans('transport.stoppage.stoppage'),
                'print_label' => 'route_stoppages_count',
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
            $headers[] = [
                'key' => Str::camel(strtolower($documentType->name)),
                'label' => $documentType->name,
                'print_label' => Str::camel(strtolower($documentType->name)),
                'sortable' => false,
                'visibility' => false,
            ];
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
        $documentTypes = $request->document_types;

        return Route::query()
            ->when($details, function ($q) use ($documentTypes) {
                $q->with(['vehicle', 'vehicle.documents' => function ($q) use ($documentTypes) {
                    $q->whereIn('type_id', $documentTypes->pluck('id'));
                }, 'vehicle.incharges' => function ($q) {
                    $q->where('start_date', '<=', today()->toDateString())
                        ->where(function ($q) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', today()->toDateString());
                        });
                }, 'vehicle.incharges.employee' => fn ($q) => $q->detail()]);
            }, function ($q) {
                $q->with('vehicle');
            })
            ->withCount('routeStoppages')
            ->withCount('routePassengers')
            ->when($request->query('vehicle'), function ($q) use ($request) {
                $q->whereHas('vehicle', function ($q) use ($request) {
                    $q->where('uuid', $request->query('vehicle'));
                });
            })
            ->byPeriod()
            ->filter([
                'App\QueryFilters\LikeMatch:name',
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $documentTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_DOCUMENT_TYPE)
            ->where('meta->has_number', true)
            ->get();

        $request->merge(['document_types' => $documentTypes]);

        return RouteListResource::collection($this->filter($request)
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
                            'uuid' => $documentType->uuid,
                            'name' => Str::camel(strtolower($documentType->name)),
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
