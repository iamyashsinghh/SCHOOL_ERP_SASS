<?php

namespace App\Imports\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Enums\OptionType;
use App\Helpers\CalHelper;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\ExpenseRecord;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use App\Support\FormatCodeNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExpenseRecordImport implements ToCollection, WithHeadingRow
{
    use FormatCodeNumber, ItemImport;

    protected $limit = 1000;

    private function codeNumber(): array
    {
        $numberPrefix = config('config.transport.vehicle_expense_number_prefix');
        $numberSuffix = config('config.transport.vehicle_expense_number_suffix');
        $digit = config('config.transport.vehicle_expense_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) ExpenseRecord::query()
            ->byTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return [
            'number' => $codeNumber,
            'digit' => $digit,
            'format' => $numberFormat,
        ];
    }

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('vehicle_expense_record');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('vehicle_expense_record', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $vehicles = Vehicle::query()
            ->byTeam()
            ->whereIn('id', $rows->pluck('vehicle_id'))
            ->get();

        $types = Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::VEHICLE_EXPENSE_TYPE])
            ->get();

        $codeDetail = $this->codeNumber();
        $number = Arr::get($codeDetail, 'number');
        $digit = Arr::get($codeDetail, 'digit');
        $numberFormat = Arr::get($codeDetail, 'format');

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {
            $date = Arr::get($row, 'date');
            $nextDueDate = Arr::get($row, 'next_due_date');
            $quantity = Arr::get($row, 'quantity');
            $unit = Arr::get($row, 'unit');
            $pricePerUnit = Arr::get($row, 'price_per_unit');
            $amount = Arr::get($row, 'amount');
            $log = Arr::get($row, 'log');
            $remarks = Arr::get($row, 'remarks');

            $vehicle = $vehicles->firstWhere('id', Arr::get($row, 'vehicle_id'));
            $type = $types->firstWhere('id', Arr::get($row, 'type_id'));

            if ($date) {
                if (is_int($date)) {
                    $date = Date::excelToDateTimeObject($date)->format('Y-m-d');
                } else {
                    $date = Carbon::parse($date)->toDateString();
                }
            }

            if ($nextDueDate) {
                if (is_int($nextDueDate)) {
                    $nextDueDate = Date::excelToDateTimeObject($nextDueDate)->format('Y-m-d');
                } else {
                    $nextDueDate = Carbon::parse($nextDueDate)->toDateString();
                }
            }

            $codeNumberDetail = $this->getCodeNumber(number: $number, digit: $digit, format: $numberFormat);

            $expenseRecord = ExpenseRecord::forceCreate([
                'number' => Arr::get($codeNumberDetail, 'number'),
                'number_format' => Arr::get($codeNumberDetail, 'format'),
                'code_number' => Arr::get($codeNumberDetail, 'code_number'),
                'vehicle_id' => $vehicle->id,
                'type_id' => $type->id,
                'date' => $date,
                'next_due_date' => Arr::get($row, 'has_next_due_date') ? $nextDueDate : null,
                'quantity' => Arr::get($row, 'has_quantity') ? $quantity : 1,
                'unit' => Arr::get($row, 'has_quantity') ? $unit : null,
                'price_per_unit' => Arr::get($row, 'has_quantity') ? $pricePerUnit : $amount,
                'amount' => Arr::get($row, 'has_quantity') ? $quantity * $pricePerUnit : $amount,
                'log' => $log,
                'remarks' => $remarks,
            ]);

            $number++;
        }

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $errors = [];

        $types = Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::VEHICLE_EXPENSE_TYPE])
            ->get();

        $vehicles = Vehicle::query()
            ->byTeam()
            ->get();

        $newRows = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'vehicle');
            $type = Arr::get($row, 'type');
            $date = Arr::get($row, 'date');
            $nextDueDate = Arr::get($row, 'next_due_date');
            $quantity = Arr::get($row, 'quantity');
            $unit = Arr::get($row, 'unit');
            $pricePerUnit = Arr::get($row, 'price_per_unit');
            $amount = Arr::get($row, 'amount');
            $log = Arr::get($row, 'log');
            $remarks = Arr::get($row, 'remarks');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.name'), 'required');
            } elseif (! $vehicles->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || Arr::get($item->registration, 'number') == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.name'), 'invalid');
            }

            if (! $date) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.date'), 'required');
            }

            if ($date && is_int($date)) {
                $date = Date::excelToDateTimeObject($date)->format('Y-m-d');
            }

            if ($date && ! CalHelper::validateDate($date)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.date'), 'invalid');
            }

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_type.expense_type'), 'required');
            }

            $expenseType = null;
            if ($type) {
                $expenseType = $types->filter(function ($item) use ($type) {
                    return strtolower($item->name) == strtolower($type);
                })->first();

                if (! $expenseType) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_type.expense_type'), 'invalid');
                }
            }

            if ($expenseType?->getMeta('has_quantity')) {
                if (! $quantity) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.quantity'), 'required');
                } elseif ($quantity && ! is_numeric($quantity)) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.quantity'), 'invalid');
                } elseif ($quantity && is_numeric($quantity) && $quantity < 0) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.quantity'), 'min', ['min' => 0]);
                }

                if (! $unit) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.unit'), 'required');
                }

                if (! $pricePerUnit) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.price_per_unit'), 'required');
                } elseif ($pricePerUnit && ! is_numeric($pricePerUnit)) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.price_per_unit'), 'invalid');
                } elseif ($pricePerUnit && is_numeric($pricePerUnit) && $pricePerUnit < 0) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.price_per_unit'), 'min', ['min' => 0]);
                }
            } else {
                if (! $amount) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.amount'), 'required');
                } elseif ($amount && ! is_numeric($amount)) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.amount'), 'invalid');
                } elseif ($amount && is_numeric($amount) && $amount < 0) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.amount'), 'min', ['min' => 0]);
                }
            }

            if ($expenseType?->getMeta('has_next_due_date')) {
                if (! $nextDueDate) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.next_due_date'), 'required');
                }

                if ($nextDueDate && is_int($nextDueDate)) {
                    $nextDueDate = Date::excelToDateTimeObject($nextDueDate)->format('Y-m-d');
                }

                if ($nextDueDate && ! CalHelper::validateDate($nextDueDate)) {
                    $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.next_due_date'), 'invalid');
                }
            }

            if (! $log) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.log'), 'required');
            } elseif ($log && ! is_numeric($log)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.log'), 'invalid');
            } elseif ($log && is_numeric($log) && $log < 0) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.expense_record.props.log'), 'min', ['min' => 0]);
            }

            $vehicle = $vehicles->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || Arr::get($item->registration, 'number') == $name;
            })->first();

            $type = $types->filter(function ($item) use ($type) {
                return strtolower($item->name) == strtolower($type);
            })->first();

            $row['vehicle_id'] = $vehicle?->id;
            $row['type_id'] = $type?->id;
            $row['has_quantity'] = (bool) $type?->getMeta('has_quantity');
            $row['has_next_due_date'] = (bool) $type?->getMeta('has_next_due_date');
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
