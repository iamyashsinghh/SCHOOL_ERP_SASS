<?php

namespace App\Imports\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Enums\Transport\Vehicle\InchargeType;
use App\Helpers\CalHelper;
use App\Models\Employee\Employee;
use App\Models\Incharge;
use App\Models\Team;
use App\Models\Transport\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class InchargeImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('vehicle_incharge');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('vehicle_incharge', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        activity()->disableLogging();

        foreach ($rows as $row) {
            $startDate = Arr::get($row, 'start_date');

            if (is_int($startDate)) {
                $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            } else {
                $startDate = Carbon::parse($startDate)->toDateString();
            }

            $endDate = Arr::get($row, 'end_date');

            if (is_int($endDate)) {
                $endDate = Date::excelToDateTimeObject($endDate)->format('Y-m-d');
            } else {
                $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : null;
            }

            Incharge::firstOrCreate([
                'model_type' => 'Vehicle',
                'model_id' => Arr::get($row, 'vehicle_id'),
                'employee_id' => Arr::get($row, 'employee_id'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'meta' => [
                    'type' => strtolower(Arr::get($row, 'type')),
                    'import_batch' => $importBatchUuid,
                    'is_imported' => true,
                ],
            ]);
        }

        $team = Team::query()
            ->whereId(auth()->user()->current_team_id)
            ->first();

        $meta = $team->meta ?? [];
        $imports['vehicle_incharge'] = Arr::get($meta, 'imports.vehicle_incharge', []);
        $imports['vehicle_incharge'][] = [
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
        $employees = Employee::query()
            ->summary()
            ->get();

        $vehicles = Vehicle::query()
            ->byTeam()
            ->get();

        $errors = [];

        $newRows = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $registrationNumber = Arr::get($row, 'registration_number');
            $employee = Arr::get($row, 'employee_code');
            $startDate = Arr::get($row, 'start_date');
            $endDate = Arr::get($row, 'end_date');
            $type = Arr::get($row, 'type');

            if (is_int($startDate)) {
                $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            }

            if ($startDate && ! CalHelper::validateDate($startDate)) {
                $errors[] = $this->setError($rowNo, trans('employee.incharge.props.start_date'), 'invalid');
            }

            if ($endDate && is_int($endDate)) {
                $endDate = Date::excelToDateTimeObject($endDate)->format('Y-m-d');
            }

            if ($endDate && ! CalHelper::validateDate($endDate)) {
                $errors[] = $this->setError($rowNo, trans('employee.incharge.props.end_date'), 'invalid');
            }

            if (! $registrationNumber) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_number'), 'required');
            } elseif (! $vehicles->firstWhere('registration.number', $registrationNumber)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_number'), 'invalid');
            }

            if (! $employee) {
                $errors[] = $this->setError($rowNo, trans('employee.props.name'), 'required');
            } elseif (! $employees->filter(function ($item) use ($employee) {
                return strtolower($item->name) == strtolower($employee) || $item->code_number == $employee;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('employee.props.name'), 'invalid');
            }

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.incharge.type'), 'required');
            } elseif (! in_array(strtolower($type), InchargeType::getKeys())) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.incharge.type'), 'invalid');
            }

            $row['employee_id'] = $employees->filter(function ($item) use ($employee) {
                return strtolower($item->name) == strtolower($employee) || $item->code_number == $employee;
            })->first()?->id ?? null;
            $row['vehicle_id'] = $vehicles->firstWhere('registration.number', $registrationNumber)?->id ?? null;
            $row['type'] = strtolower($type);
            $row['start_date'] = $startDate ? Carbon::parse($startDate)->toDateString() : null;
            $row['end_date'] = $endDate ? Carbon::parse($endDate)->toDateString() : null;

            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
