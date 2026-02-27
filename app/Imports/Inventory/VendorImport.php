<?php

namespace App\Imports\Inventory;

use App\Concerns\ItemImport;
use App\Enums\Finance\LedgerGroup;
use App\Models\Finance\Ledger;
use App\Models\Finance\LedgerType;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VendorImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('vendor');

        $errors = $this->validate($rows);

        $this->checkForErrors('vendor', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        $ledgerType = LedgerType::query()
            ->byTeam()
            ->whereType(LedgerGroup::SUNDRY_CREDITOR)
            ->first();

        activity()->disableLogging();

        foreach ($rows as $index => $row) {
            $name = Arr::get($row, 'name');
            $ledgerTypeId = $ledgerType->id;
            $contactNumber = Arr::get($row, 'contact_number');
            $email = Arr::get($row, 'email');
            $addressLine1 = Arr::get($row, 'address_line1');
            $addressLine2 = Arr::get($row, 'address_line2');
            $city = Arr::get($row, 'city');
            $state = Arr::get($row, 'state');
            $zipcode = Arr::get($row, 'zipcode');
            $country = Arr::get($row, 'country');

            $accountName = Arr::get($row, 'account_name');
            $accountNumber = Arr::get($row, 'account_number');
            $bankName = Arr::get($row, 'bank_name');
            $branchName = Arr::get($row, 'branch_name');
            $bankCode = Arr::get($row, 'branch_code');
            $branchAddress = Arr::get($row, 'branch_address');

            Ledger::forceCreate([
                'name' => $name,
                'ledger_type_id' => $ledgerTypeId,
                'contact_number' => $contactNumber,
                'email' => $email,
                'address' => [
                    'address_line1' => $addressLine1,
                    'address_line2' => $addressLine2,
                    'city' => $city,
                    'state' => $state,
                    'zipcode' => $zipcode,
                    'country' => $country,
                ],
                'account' => [
                    'name' => $accountName,
                    'number' => $accountNumber,
                    'bank_name' => $bankName,
                    'branch_name' => $branchName,
                    'branch_code' => $bankCode,
                    'branch_address' => $branchAddress,
                ],
            ]);
        }

        $team = Team::query()
            ->whereId(auth()->user()->current_team_id)
            ->first();

        $meta = $team->meta ?? [];
        $imports['vendor'] = Arr::get($meta, 'imports.vendor', []);
        $imports['vendor'][] = [
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
        $ledgerType = LedgerType::query()
            ->byTeam()
            ->whereType(LedgerGroup::SUNDRY_CREDITOR)
            ->first();

        if (! $ledgerType) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('finance.ledger_type.ledger_type')])]);
        }

        $existingNames = Ledger::query()
            ->whereLedgerTypeId($ledgerType->id)
            ->pluck('name')
            ->all();

        $errors = [];

        $newNames = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'name');
            $contactNumber = Arr::get($row, 'contact_number');
            $email = Arr::get($row, 'email');
            $addressLine1 = Arr::get($row, 'address_line1');
            $addressLine2 = Arr::get($row, 'address_line2');
            $city = Arr::get($row, 'city');
            $state = Arr::get($row, 'state');
            $zipcode = Arr::get($row, 'zipcode');
            $country = Arr::get($row, 'country');

            $accountName = Arr::get($row, 'account_name');
            $accountNumber = Arr::get($row, 'account_number');
            $bankName = Arr::get($row, 'bank_name');
            $branchName = Arr::get($row, 'branch_name');
            $bankCode = Arr::get($row, 'branch_code');
            $branchAddress = Arr::get($row, 'branch_address');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('inventory.vendor.props.name'), 'required');
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $errors[] = $this->setError($rowNo, trans('inventory.vendor.props.name'), 'min_max', ['min' => 2, 'max' => 100]);
            } elseif (in_array($name, $existingNames)) {
                $errors[] = $this->setError($rowNo, trans('inventory.vendor.props.name'), 'exists');
            } elseif (in_array($name, $newNames)) {
                $errors[] = $this->setError($rowNo, trans('inventory.vendor.props.name'), 'duplicate');
            }

            if (! $contactNumber) {
                $errors[] = $this->setError($rowNo, trans('inventory.vendor.props.contact_number'), 'required');
            } elseif (strlen($contactNumber) < 2 || strlen($contactNumber) > 100) {
                $errors[] = $this->setError($rowNo, trans('inventory.vendor.props.contact_number'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.email'), 'invalid');
            }

            if ($addressLine1 && (strlen($addressLine1) < 2 || strlen($addressLine1) > 100)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.address_line1'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($addressLine2 && (strlen($addressLine2) < 2 || strlen($addressLine2) > 100)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.address_line2'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($city && (strlen($city) < 2 || strlen($city) > 100)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.city'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($state && (strlen($state) < 2 || strlen($state) > 100)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.state'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($zipcode && (strlen($zipcode) < 2 || strlen($zipcode) > 20)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.zipcode'), 'min_max', ['min' => 2, 'max' => 20]);
            }

            if ($country && (strlen($country) < 2 || strlen($country) > 100)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.address.country'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($accountName && (strlen($accountName) < 2 || strlen($accountName) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.name'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($accountNumber && (strlen($accountNumber) < 2 || strlen($accountNumber) > 30)) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.number'), 'min_max', ['min' => 2, 'max' => 30]);
            }

            if ($bankName && (strlen($bankName) < 2 || strlen($bankName) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.bank_name'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($branchName && (strlen($branchName) < 2 || strlen($branchName) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.branch_name'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($bankCode && (strlen($bankCode) < 2 || strlen($bankCode) > 20)) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.branch_code'), 'min_max', ['min' => 2, 'max' => 20]);
            }

            if ($branchAddress && (strlen($branchAddress) < 2 || strlen($branchAddress) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.branch_address'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            $newNames[] = $name;
        }

        return $errors;
    }
}
