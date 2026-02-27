<?php

namespace App\Imports\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Enums\OptionType;
use App\Enums\Transport\Vehicle\FuelType;
use App\Enums\Transport\Vehicle\Ownership;
use App\Helpers\CalHelper;
use App\Models\Option;
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

class VehicleImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('vehicle');

        $errors = $this->validate($rows);

        $this->checkForErrors('vehicle', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        activity()->disableLogging();

        $vehicleTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_TYPE)
            ->get();

        foreach ($rows as $index => $row) {
            $name = Arr::get($row, 'name');
            $registrationNumber = Arr::get($row, 'registration_number');
            $registrationPlace = Arr::get($row, 'registration_place');
            $registrationDate = Arr::get($row, 'registration_date');
            $type = Arr::get($row, 'type');
            $engineNumber = Arr::get($row, 'engine_number');
            $chassisNumber = Arr::get($row, 'chassis_number');
            $cubicCapacity = Arr::get($row, 'cubic_capacity');
            $color = Arr::get($row, 'color');
            $modelNumber = Arr::get($row, 'model_number');
            $make = Arr::get($row, 'make');
            $class = Arr::get($row, 'class');
            $seatingCapacity = Arr::get($row, 'seating_capacity');
            $maxSeatingAllowed = Arr::get($row, 'max_seating_allowed', $seatingCapacity);
            $fuelType = Arr::get($row, 'fuel_type');
            $fuelCapacity = Arr::get($row, 'fuel_capacity');
            $ownership = Arr::get($row, 'ownership');
            $ownershipDate = Arr::get($row, 'ownership_date');
            $ownerName = Arr::get($row, 'owner_name');
            $ownerPhone = Arr::get($row, 'owner_phone');
            $ownerEmail = Arr::get($row, 'owner_email');
            $ownerAddress = Arr::get($row, 'owner_address');

            $registrationDate = Arr::get($row, 'registration_date');

            if (is_int($registrationDate)) {
                $registrationDate = Date::excelToDateTimeObject($registrationDate)->format('Y-m-d');
            } else {
                $registrationDate = Carbon::parse($registrationDate)->toDateString();
            }

            $ownershipDate = Arr::get($row, 'ownership_date');

            if ($ownershipDate) {
                if (is_int($ownershipDate)) {
                    $ownershipDate = Date::excelToDateTimeObject($ownershipDate)->format('Y-m-d');
                } else {
                    $ownershipDate = Carbon::parse($ownershipDate)->toDateString();
                }
            }

            $typeId = $vehicleTypes->filter(function ($vehicleType) use ($type) {
                return strtolower($vehicleType->name) === strtolower($type);
            })->first()?->id;

            Vehicle::forceCreate([
                'team_id' => auth()->user()->current_team_id,
                'name' => $name,
                'type_id' => $typeId,
                'registration' => [
                    'number' => $registrationNumber,
                    'place' => $registrationPlace,
                    'date' => $registrationDate,
                    'chassis_number' => $chassisNumber,
                    'engine_number' => $engineNumber,
                    'cubic_capacity' => $cubicCapacity,
                    'color' => $color,
                ],
                'model_number' => $modelNumber,
                'make' => $make,
                'class' => $class,
                'fuel_type' => strtolower($fuelType),
                'fuel_capacity' => $fuelCapacity,
                'seating_capacity' => $seatingCapacity,
                'max_seating_allowed' => $maxSeatingAllowed ?? $seatingCapacity,
                'owner' => [
                    'ownership' => strtolower($ownership),
                    'ownership_date' => $ownershipDate,
                    'name' => $ownerName,
                    'phone' => $ownerPhone,
                    'email' => $ownerEmail,
                    'address' => $ownerAddress,
                ],
            ]);
        }

        $team = Team::query()
            ->whereId(auth()->user()->current_team_id)
            ->first();

        $meta = $team->meta ?? [];
        $imports['vehicle'] = Arr::get($meta, 'imports.vehicle', []);
        $imports['vehicle'][] = [
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
        $existingNames = Vehicle::query()
            ->byTeam()
            ->pluck('name')
            ->all();

        $existingRegistrationNumbers = Vehicle::query()
            ->byTeam()
            ->get()
            ->pluck('registration.number')
            ->all();

        $vehicleTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::VEHICLE_TYPE)
            ->get();

        $errors = [];

        $newNames = [];
        $newRegistrationNumbers = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'name');
            $registrationNumber = Arr::get($row, 'registration_number');
            $registrationPlace = Arr::get($row, 'registration_place');
            $registrationDate = Arr::get($row, 'registration_date');
            $type = Arr::get($row, 'type');
            $engineNumber = Arr::get($row, 'engine_number');
            $chassisNumber = Arr::get($row, 'chassis_number');
            $cubicCapacity = Arr::get($row, 'cubic_capacity');
            $color = Arr::get($row, 'color');
            $modelNumber = Arr::get($row, 'model_number');
            $make = Arr::get($row, 'make');
            $class = Arr::get($row, 'class');
            $seatingCapacity = Arr::get($row, 'seating_capacity');
            $maxSeatingAllowed = Arr::get($row, 'max_seating_allowed');
            $fuelType = Arr::get($row, 'fuel_type');
            $fuelCapacity = Arr::get($row, 'fuel_capacity');
            $ownership = Arr::get($row, 'ownership');
            $ownershipDate = Arr::get($row, 'ownership_date');
            $ownerName = Arr::get($row, 'owner_name');
            $ownerPhone = Arr::get($row, 'owner_phone');
            $ownerEmail = Arr::get($row, 'owner_email');
            $ownerAddress = Arr::get($row, 'owner_address');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.name'), 'required');
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.name'), 'min_max', ['min' => 2, 'max' => 100]);
            } elseif (in_array($name, $existingNames)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.name'), 'exists');
            } elseif (in_array($name, $newNames)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.name'), 'duplicate');
            }

            if (! $registrationNumber) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_number'), 'required');
            } elseif (strlen($registrationNumber) < 2 || strlen($registrationNumber) > 100) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_number'), 'min_max', ['min' => 2, 'max' => 100]);
            } elseif (in_array($registrationNumber, $existingRegistrationNumbers)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_number'), 'exists');
            } elseif (in_array($registrationNumber, $newRegistrationNumbers)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_number'), 'duplicate');
            }

            if (! $registrationDate) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_date'), 'required');
            }

            if (is_int($registrationDate)) {
                $registrationDate = Date::excelToDateTimeObject($registrationDate)->format('Y-m-d');
            }

            if ($registrationDate && ! CalHelper::validateDate($registrationDate)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.registration_date'), 'invalid');
            }

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.type'), 'required');
            } elseif (! $vehicleTypes->filter(function ($vehicleType) use ($type) {
                return strtolower($vehicleType->name) === strtolower($type);
            })) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.type'), 'invalid');
            }

            if ($fuelType && ! in_array(strtolower($fuelType), FuelType::getKeys())) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.fuel_type'), 'invalid');
            }

            if ($seatingCapacity && ! is_int($seatingCapacity)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.seating_capacity'), 'integer');
            }

            if ($maxSeatingAllowed && ! is_int($maxSeatingAllowed)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.max_seating_allowed'), 'integer');
            }

            if ($fuelCapacity && ! is_int($fuelCapacity)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.fuel_capacity'), 'integer');
            }

            if ($ownership && ! in_array(strtolower($ownership), Ownership::getKeys())) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.ownership'), 'invalid');
            }

            if (! $ownerName) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_name'), 'required');
            } elseif (strlen($ownerName) < 2 || strlen($ownerName) > 100) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_name'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if (! $ownerPhone) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_phone'), 'required');
            } elseif (strlen($ownerPhone) < 2 || strlen($ownerPhone) > 20) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_phone'), 'min_max', ['min' => 2, 'max' => 20]);
            }

            if (! $ownerEmail) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_email'), 'required');
            } elseif (strlen($ownerEmail) < 2 || strlen($ownerEmail) > 100) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_email'), 'min_max', ['min' => 2, 'max' => 100]);
            } elseif (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.owner_email'), 'email');
            }

            if ($ownershipDate && is_int($ownershipDate)) {
                $ownershipDate = Date::excelToDateTimeObject($ownershipDate)->format('Y-m-d');
            }

            if ($ownershipDate && ! CalHelper::validateDate($ownershipDate)) {
                $errors[] = $this->setError($rowNo, trans('transport.vehicle.props.ownership_date'), 'invalid');
            }

            $newNames[] = $name;
            $newRegistrationNumbers[] = $registrationNumber;
        }

        return $errors;
    }
}
