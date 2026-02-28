<?php

namespace App\Services\Transport\Vehicle;

use App\Contracts\ListGenerator;
use App\Http\Resources\Transport\Vehicle\FuelRecordResource;
use App\Models\Tenant\Transport\Vehicle\FuelRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FuelRecordListService extends ListGenerator
{
    protected $allowedSorts = ['created_at'];

    protected $defaultSort = 'created_at';

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
                'key' => 'fuelType',
                'label' => trans('transport.vehicle.props.fuel_type'),
                'print_label' => 'vehicle.fuel_type.label',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'make',
                'label' => trans('transport.vehicle.props.make'),
                'print_label' => 'vehicle.make',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'date',
                'label' => trans('transport.vehicle.fuel_record.props.date'),
                'print_label' => 'date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'quantity',
                'label' => trans('transport.vehicle.fuel_record.props.quantity'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'pricePerUnit',
                'label' => trans('transport.vehicle.fuel_record.props.price_per_unit'),
                'print_label' => 'price_per_unit.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'cost',
                'label' => trans('transport.vehicle.fuel_record.props.cost'),
                'print_label' => 'cost.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'previousLog',
                'label' => trans('transport.vehicle.fuel_record.props.previous_log'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'log',
                'label' => trans('transport.vehicle.fuel_record.props.log'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'distanceCovered',
                'label' => trans('transport.vehicle.fuel_record.props.distance_covered'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'mileage',
                'label' => trans('transport.vehicle.fuel_record.props.mileage'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'vendor',
                'label' => trans('inventory.vendor.vendor'),
                'print_label' => 'vendor.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'billNumber',
                'label' => trans('transport.vehicle.fuel_record.props.bill_number'),
                'print_label' => 'bill_number',
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
        $vehicles = Str::toArray($request->query('vehicles'));

        return FuelRecord::query()
            ->whereHas('vehicle', function ($q) {
                $q->byTeam();
            })
            ->with('vehicle', 'vendor')
            ->when($request->query('vehicle'), function ($q, $vehicle) {
                $q->whereHas('vehicle', function ($q) use ($vehicle) {
                    $q->where('uuid', $vehicle);
                });
            })
            ->when($vehicles, function ($q, $vehicles) {
                $q->whereHas('vehicle', function ($q) use ($vehicles) {
                    $q->whereIn('uuid', $vehicles);
                });
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\DateBetween:start_date,end_date,date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $summary = $this->filter($request)
            ->selectRaw('AVG(price_per_unit) as average_price_per_unit')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('ROUND(SUM(quantity * price_per_unit), 2) as total_cost')
            ->first();

        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->when($request->query('output') == 'export_all_excel', function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            });

        return FuelRecordResource::collection($records)
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                    'has_footer' => true,
                ],
                'footers' => [
                    ['key' => 'vehicle', 'label' => trans('general.total')],
                    ['key' => 'fuelType', 'label' => ''],
                    ['key' => 'make', 'label' => ''],
                    ['key' => 'date', 'label' => ''],
                    ['key' => 'quantity', 'label' => round($summary->total_quantity, 2)],
                    ['key' => 'pricePerUnit', 'label' => \Price::from($summary?->average_price_per_unit)?->formatted],
                    ['key' => 'cost', 'label' => \Price::from($summary?->total_cost)?->formatted],
                    ['key' => 'previousLog', 'label' => ''],
                    ['key' => 'log', 'label' => ''],
                    ['key' => 'distanceCovered', 'label' => ''],
                    ['key' => 'mileage', 'label' => ''],
                    ['key' => 'vendor', 'label' => ''],
                    ['key' => 'billNumber', 'label' => ''],
                    ['key' => 'createdAt', 'label' => ''],
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
