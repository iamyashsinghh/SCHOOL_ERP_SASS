<?php

namespace App\Services\Transport\Vehicle;

use App\Contracts\ListGenerator;
use App\Http\Resources\Transport\Vehicle\TripRecordResource;
use App\Models\Tenant\Transport\Vehicle\TripRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripRecordListService extends ListGenerator
{
    protected $allowedSorts = ['log', 'created_at'];

    protected $defaultSort = 'log';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'vehicle',
                'label' => trans('transport.vehicle.vehicle'),
                'print_label' => 'vehicle.registration_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'purpose',
                'label' => trans('transport.vehicle.trip_purpose.trip_purpose'),
                'print_label' => 'purpose.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'date',
                'label' => trans('transport.vehicle.trip_record.props.date'),
                'print_label' => 'date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'log',
                'label' => trans('transport.vehicle.trip_record.props.log'),
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
        return TripRecord::query()
            ->whereHas('vehicle', function ($q) {
                $q->byTeam();
            })
            ->with('vehicle', 'purpose')
            ->when($request->query('vehicle'), function ($q, $vehicle) {
                $q->whereHas('vehicle', function ($q) use ($vehicle) {
                    $q->where('uuid', $vehicle);
                });
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\DateBetween:start_date,end_date,date',
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

        return TripRecordResource::collection($records)
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
