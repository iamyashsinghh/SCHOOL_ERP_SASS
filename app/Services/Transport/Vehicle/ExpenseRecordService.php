<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\OptionType;
use App\Http\Resources\Transport\Vehicle\Config\ExpenseTypeResource;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Option;
use App\Models\Transport\Vehicle\ExpenseRecord;
use App\Models\Transport\Vehicle\Vehicle;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ExpenseRecordService
{
    use FormatCodeNumber;

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

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(Request $request): array
    {
        $vehicles = VehicleResource::collection(Vehicle::query()
            ->byTeam()
            ->get());

        $types = ExpenseTypeResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_EXPENSE_TYPE)
            ->get());

        return compact('vehicles', 'types');
    }

    public function create(Request $request): ExpenseRecord
    {
        \DB::beginTransaction();

        $vehicleExpenseRecord = ExpenseRecord::forceCreate($this->formatParams($request));

        $vehicleExpenseRecord->addMedia($request);

        $vehicleExpenseRecord->addReminder($request);

        \DB::commit();

        return $vehicleExpenseRecord;
    }

    private function formatParams(Request $request, ?ExpenseRecord $vehicleExpenseRecord = null): array
    {
        $formatted = [
            'vehicle_id' => $request->vehicle_id,
            'type_id' => $request->type_id,
            'date' => $request->date,
            'amount' => $request->amount,
            'log' => $request->log,
            'next_due_date' => $request->next_due_date,
            'remarks' => $request->remarks,
        ];

        if ($request->has_quantity) {
            $formatted['quantity'] = $request->quantity;
            $formatted['unit'] = $request->unit;
            $formatted['price_per_unit'] = $request->price_per_unit;
            $formatted['amount'] = $request->quantity * $request->price_per_unit;
        } else {
            $formatted['quantity'] = 1;
            $formatted['unit'] = null;
            $formatted['price_per_unit'] = $request->amount;
        }

        if (! $vehicleExpenseRecord) {
            $codeNumberDetail = $this->codeNumber();

            $formatted['number_format'] = Arr::get($codeNumberDetail, 'number_format');
            $formatted['number'] = Arr::get($codeNumberDetail, 'number');
            $formatted['code_number'] = Arr::get($codeNumberDetail, 'code_number');
        }

        return $formatted;
    }

    public function update(Request $request, ExpenseRecord $vehicleExpenseRecord): void
    {
        \DB::beginTransaction();

        $vehicleExpenseRecord->forceFill($this->formatParams($request, $vehicleExpenseRecord))->save();

        $vehicleExpenseRecord->updateMedia($request);

        $vehicleExpenseRecord->updateReminder($request);

        \DB::commit();
    }

    public function deletable(ExpenseRecord $vehicleExpenseRecord): void
    {
        //
    }
}
