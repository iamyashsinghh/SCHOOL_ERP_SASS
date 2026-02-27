<?php

namespace App\Services\Inventory;

use App\Contracts\ListGenerator;
use App\Http\Resources\Inventory\StockItemCopyListResource;
use App\Models\Asset\Building\Room;
use App\Models\Inventory\StockItemCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class StockItemCopyListService extends ListGenerator
{
    protected $allowedSorts = ['created_at'];

    protected $defaultSort = 'stock_item_copies.created_at';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'codeNumber',
                'label' => trans('inventory.stock_item.copy.props.code_number_short'),
                'print_label' => 'code_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'stockItem',
                'label' => trans('inventory.stock_item.stock_item'),
                'print_label' => 'item.name',
                'print_sub_label' => 'item.code',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'price',
                'label' => trans('inventory.stock_item.props.unit_price'),
                'print_label' => 'price.formatted',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'holdStatus',
                'label' => trans('inventory.stock_item.copy.props.hold_status'),
                'print_label' => 'hold_status.label',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('inventory.stock_category.stock_category'),
                'print_label' => 'category',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'inventory',
                'label' => trans('inventory.inventory'),
                'print_label' => 'inventory',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'place',
                'label' => trans('inventory.stock_item.props.place'),
                'print_label' => 'place.name',
                'print_sub_label' => 'place.floor.name_with_block',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'condition',
                'label' => trans('inventory.stock_item.props.condition'),
                'print_label' => 'condition.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'vendor',
                'label' => trans('library.book_addition.props.vendor'),
                'print_label' => 'vendor',
                'print_sub_label' => 'invoice_number',
                'print_sub_label_2' => 'invoice_date',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'invoiceNumber',
                'label' => trans('library.book_addition.props.invoice_number'),
                'print_label' => 'invoice_number',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'invoiceDate',
                'label' => trans('library.book_addition.props.invoice_date'),
                'print_label' => 'invoice_date.formatted',
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
            array_unshift($headers, ['key' => 'selectAll', 'sortable' => false]);
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $places = Str::toArray($request->query('places'));

        $tagsIncluded = Str::toArray($request->query('tags_included'));
        $tagsExcluded = Str::toArray($request->query('tags_excluded'));

        $date = $request->query('date', today()->format('Y-m-d'));

        return StockItemCopy::query()
            ->with(['condition', 'item', 'place' => fn ($q) => $q->withFloorAndBlock()])
            ->join('stock_items', 'stock_items.id', '=', 'stock_item_copies.stock_item_id')
            ->join('stock_categories', 'stock_categories.id', '=', 'stock_items.stock_category_id')
            ->join('inventories', 'inventories.id', '=', 'stock_categories.inventory_id')
            ->where('inventories.team_id', auth()->user()->current_team_id)
            ->select(
                'stock_item_copies.*',
                'stock_items.code',
                'stock_categories.name as category',
                'inventories.name as inventory',
            )
            ->when($request->query('code_number'), function ($query) use ($request) {
                $query->where('stock_item_copies.code_number', '=', $request->query('code_number'));
            })
            ->when($request->query('name'), function ($query) use ($request) {
                $query->whereHas('stock_item', function ($query) use ($request) {
                    $query->where('name', 'like', '%'.$request->query('name').'%');
                });
            })
            ->when($places, function ($q, $places) {
                $q->whereHasMorph(
                    'place',
                    [Room::class],
                    function (Builder $query) use ($places) {
                        $query->whereIn('uuid', $places);
                    }
                );
            })
            ->when($tagsIncluded, function ($q, $tagsIncluded) {
                $q->whereHas('tags', function ($q) use ($tagsIncluded) {
                    $q->whereIn('name', $tagsIncluded);
                });
            })
            ->when($tagsExcluded, function ($q, $tagsExcluded) {
                $q->whereDoesntHave('tags', function ($q) use ($tagsExcluded) {
                    $q->whereIn('name', $tagsExcluded);
                });
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $stockItemCopies = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->when($request->query('output') == 'export_all_excel', function ($q) {
                return $q->get();
            }, function ($q) {
                return $q->paginate((int) $this->getPageLength(), ['*'], 'current_page');
            });

        $stockItemIds = $stockItemCopies->pluck('stock_item_id');

        return StockItemCopyListResource::collection($stockItemCopies)
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'filename' => 'Inventory Stock Item Copy List',
                    'sno' => $this->getSno(),
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
