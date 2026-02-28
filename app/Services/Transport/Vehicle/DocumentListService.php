<?php

namespace App\Services\Transport\Vehicle;

use App\Contracts\ListGenerator;
use App\Http\Resources\Transport\Vehicle\DocumentResource;
use App\Models\Tenant\Document;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'expiry_in_days'];

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
                'key' => 'title',
                'label' => trans('transport.vehicle.document.props.title'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'number',
                'label' => trans('transport.vehicle.document.props.number'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'type',
                'label' => trans('transport.vehicle.document.props.type'),
                'print_label' => 'type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'startDate',
                'label' => trans('transport.vehicle.document.props.start_date'),
                'print_label' => 'start_date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'endDate',
                'label' => trans('transport.vehicle.document.props.end_date'),
                'print_label' => 'end_date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'expiryInDays',
                'label' => trans('transport.vehicle.document.props.expiry_in_days'),
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

    public function filter(Request $request): Builder
    {
        $expiryInDays = $request->query('expiry_in_days');
        $expiryStatus = $request->query('expiry_status');

        return Document::query()
            ->whereHasMorph(
                'documentable',
                [Vehicle::class],
                function (Builder $query) {
                    $query->byTeam();
                }
            )
            ->with('documentable', 'type')
            ->select('documents.*')
            ->selectRaw('DATEDIFF(end_date, CURDATE()) as expiry_in_days')
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
            ->when($request->query('vehicle'), function ($q, $vehicle) {
                $q->whereHas('documentable', function ($q) use ($vehicle) {
                    $q->where('uuid', $vehicle);
                });
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\LikeMatch:title',
                'App\QueryFilters\ExactMatch:number',
                'App\QueryFilters\DateBetween:issue_start_date,issue_end_date,issue_date',
                'App\QueryFilters\DateBetween:start_start_date,start_end_date,start_date',
                'App\QueryFilters\DateBetween:end_start_date,end_end_date,end_date',
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

        return DocumentResource::collection($records)
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
