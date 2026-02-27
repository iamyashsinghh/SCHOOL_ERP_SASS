<?php

namespace App\Imports\Inventory;

use App\Actions\CreateTag;
use App\Concerns\ItemImport;
use App\Enums\Inventory\ItemTrackingType;
use App\Enums\Inventory\ItemType;
use App\Enums\OptionType;
use App\Helpers\CalHelper;
use App\Models\Asset\Building\Room;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockCategory;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\StockItemCopy;
use App\Models\Option;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockItemImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 100;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('stock_item');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('stock_item', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        activity()->disableLogging();

        foreach ($rows as $row) {
            $stockCategoryId = Arr::get($row, 'stock_category_id');
            $unitId = Arr::get($row, 'unit_id');

            $stockItem = StockItem::firstOrCreate([
                'stock_category_id' => $stockCategoryId,
                'name' => Arr::get($row, 'name'),
                'code' => Arr::get($row, 'code'),
            ]);

            $stockItem->unit = Arr::get($row, 'unit');
            $stockItem->type = Str::snake(Arr::get($row, 'type'));
            $stockItem->tracking_type = Str::snake(Arr::get($row, 'tracking_type'));
            $stockItem->description = Arr::get($row, 'description');
            $stockItem->setMeta([
                'hsn' => Arr::get($row, 'hsn'),
                'import_batch' => $importBatchUuid,
                'is_imported' => true,
            ]);
            $stockItem->save();

            if (Arr::get($row, 'tags')) {
                $tags = Str::toArray(Arr::get($row, 'tags'));
                $tags = (new CreateTag)->execute($tags);
                $stockItem->tags()->sync($tags);
            }

            if (Arr::get($row, 'quantity', 0)) {
                $stockBalance = StockBalance::firstOrCreate([
                    'stock_item_id' => $stockItem->id,
                    'place_type' => 'Room',
                    'place_id' => Arr::get($row, 'place_id'),
                ]);

                $stockBalance->opening_quantity += Arr::get($row, 'quantity', 0);
                $stockBalance->save();

                if ($stockItem->tracking_type === ItemTrackingType::UNIQUE) {

                    $invoiceDate = Arr::get($row, 'invoice_date');
                    if ($invoiceDate) {
                        if (is_int($invoiceDate)) {
                            $invoiceDate = Date::excelToDateTimeObject($invoiceDate)->format('Y-m-d');
                        } else {
                            $invoiceDate = Carbon::parse($invoiceDate)->toDateString();
                        }
                    }

                    $stockItemCopyNumber = StockItemCopy::query()
                        ->where('stock_item_id', $stockItem->id)
                        ->max('number') + 1;

                    $stockItemCopy = StockItemCopy::forceCreate([
                        'number' => $stockItemCopyNumber,
                        'code_number' => $stockItem->code.'-'.($stockItemCopyNumber),
                        'stock_item_id' => $stockItem->id,
                        'vendor' => Arr::get($row, 'vendor'),
                        'invoice_date' => $invoiceDate,
                        'invoice_number' => Arr::get($row, 'invoice_number'),
                        'price' => Arr::get($row, 'unit_price', 0),
                        'place_type' => 'Room',
                        'place_id' => Arr::get($row, 'place_id'),
                        'meta' => [
                            'hsn' => Arr::get($row, 'hsn'),
                        ],
                    ]);

                    if (Arr::get($row, 'tags')) {
                        $tags = Str::toArray(Arr::get($row, 'tags'));
                        $tags = (new CreateTag)->execute($tags);
                        $stockItemCopy->tags()->sync($tags);
                    }
                }
            }
        }

        $team = Team::query()
            ->whereId(auth()->user()->current_team_id)
            ->first();

        $meta = $team->meta ?? [];
        $imports['stock_item'] = Arr::get($meta, 'imports.stock_item', []);
        $imports['stock_item'][] = [
            'uuid' => $importBatchUuid,
            'total' => count($rows),
            'created_at' => now()->toDateTimeString(),
        ];

        $meta['imports'] = $imports;
        $team->meta = $meta;
        $team->save();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $items = StockItem::byTeam()->get();
        $existingNames = $items->pluck('name')->all();
        $existingCodes = $items->pluck('code')->all();

        $categories = StockCategory::query()
            ->byTeam()
            ->get();

        $units = Option::query()
            ->where('type', OptionType::UNIT)
            ->get();

        $types = ItemType::getKeys();
        $trackingTypes = ItemTrackingType::getKeys();

        $rooms = Room::query()
            ->byTeam()
            ->get();

        $errors = [];

        $newRows = [];

        $newNames = [];
        $newCodes = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'name');
            $category = Arr::get($row, 'category');
            $code = Arr::get($row, 'code');
            $type = Arr::get($row, 'type');
            $trackingType = Arr::get($row, 'tracking_type');
            $unit = Arr::get($row, 'unit');
            $unitPrice = Arr::get($row, 'unit_price');
            $place = Arr::get($row, 'place');
            $quantity = Arr::get($row, 'quantity', 0);
            $description = Arr::get($row, 'description');
            $vendor = Arr::get($row, 'vendor');
            $invoiceDate = Arr::get($row, 'invoice_date');
            $invoiceNumber = Arr::get($row, 'invoice_number');
            $hsn = Arr::get($row, 'hsn');
            $tags = Arr::get($row, 'tags');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.name'), 'required');
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.name'), 'min_max', ['min' => 2, 'max' => 100]);
                // } elseif (in_array($name, $existingNames)) {
                // Allow duplicate name to add new items
                //     $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.name'), 'exists');
            } elseif (in_array($name, $newNames)) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.name'), 'duplicate');
            }

            if (! $code) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.code'), 'required');
            } elseif (strlen($code) < 1 || strlen($code) > 50) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.code'), 'min_max', ['min' => 2, 'max' => 50]);
                // } elseif (in_array($code, $existingCodes)) {
                // Allow duplicate code to add new items
                //     $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.code'), 'exists');
            } elseif (in_array($code, $newCodes)) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.code'), 'duplicate');
            }

            if (! $category) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_category.stock_category'), 'required');
            } elseif (! in_array(strtolower($category), $categories->pluck('name')->map(fn ($name) => strtolower($name))->all())) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_category.stock_category'), 'invalid');
            }

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.type'), 'required');
            } elseif (! in_array(Str::snake($type), $types)) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.type'), 'invalid');
            }

            if (! $trackingType) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.tracking_type'), 'required');
            } elseif (! in_array(Str::snake($trackingType), $trackingTypes)) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.tracking_type'), 'invalid');
            }

            if (! $unit) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.unit'), 'required');
            } elseif (! $units->filter(function ($unitItem) use ($unit) {
                return strtolower($unitItem->name) === strtolower($unit);
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.unit'), 'invalid');
            }

            if ($quantity) {
                if (! is_numeric($quantity)) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.quantity'), 'numeric');
                }

                if ($quantity < 1) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.quantity'), 'min', ['min' => 1]);
                }

                if ($unitPrice && ! is_numeric($unitPrice)) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.unit_price'), 'numeric');
                } elseif ($unitPrice && $unitPrice < 0) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.unit_price'), 'min', ['min' => 0]);
                }

                if (! $place) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.place'), 'required');
                } elseif (! $rooms->filter(function ($item) use ($place) {
                    return strtolower($item->number) == strtolower($place);
                })->first()) {
                    $errors[] = $this->setError($rowNo, trans('inventory.place'), 'invalid');
                }

                if ($vendor && strlen($vendor) > 100) {
                    $errors[] = $this->setError($rowNo, trans('inventory.vendor.vendor'), 'min_max', ['min' => 2, 'max' => 100]);
                }

                if ($invoiceDate && is_int($invoiceDate)) {
                    $invoiceDate = Date::excelToDateTimeObject($invoiceDate)->format('Y-m-d');
                }

                if ($invoiceDate && ! CalHelper::validateDate($invoiceDate)) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.invoice_date'), 'invalid');
                }

                if ($invoiceNumber && strlen($invoiceNumber) > 100) {
                    $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.invoice_number'), 'min_max', ['min' => 2, 'max' => 100]);
                }
            }

            if ($description && (strlen($description) < 2 || strlen($description) > 100)) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.description'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($hsn && (strlen($hsn) < 2 || strlen($hsn) > 100)) {
                $errors[] = $this->setError($rowNo, trans('inventory.stock_item.props.hsn'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($tags) {
                $tags = Str::toArray($tags);
                foreach ($tags as $tag) {
                    if (strlen($tag) < 2 || strlen($tag) > 50) {
                        $errors[] = $this->setError($rowNo, trans('general.tag'), 'min_max', ['min' => 2, 'max' => 50]);
                    }
                }
            }

            $newNames[] = $name;
            $newCodes[] = $code;

            if ($category) {
                $category = $categories->filter(function ($item) use ($category) {
                    return strtolower($item->name) == strtolower($category);
                })->first();

                $row['stock_category_id'] = $category?->id;
            }

            if ($unit) {
                $unit = $units->filter(function ($item) use ($unit) {
                    return strtolower($item->name) == strtolower($unit);
                })->first();

                $row['unit_id'] = $unit?->id;
            }

            if ($quantity && $place) {
                $room = $rooms->filter(function ($item) use ($place) {
                    return strtolower($item->number) == strtolower($place);
                })->first();

                $row['place_id'] = $room?->id;
                $row['place_type'] = 'Room';
            }

            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
