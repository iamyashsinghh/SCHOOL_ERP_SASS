<?php

namespace App\Imports\Employee;

use App\Concerns\ItemImport;
use App\Models\Account;
use App\Models\Employee\Employee;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('employee_account');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('employee_account', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $employees = Employee::query()
            ->byTeam()
            ->whereIn('id', $rows->pluck('employee_id'))
            ->get();

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {
            $accountName = Arr::get($row, 'name');
            $number = Arr::get($row, 'number');
            $bankName = Arr::get($row, 'bank_name');
            $branchName = Arr::get($row, 'branch_name');
            $bankCode1 = Arr::get($row, 'bank_code1');
            $bankCode2 = Arr::get($row, 'bank_code2');
            $bankCode3 = Arr::get($row, 'bank_code3');

            $employee = $employees->firstWhere('id', Arr::get($row, 'employee_id'));

            $data = [
                'accountable_type' => 'Contact',
                'accountable_id' => $employee->contact_id,
                'number' => $number,
            ];

            $account = Account::firstOrCreate($data);
            $account->name = $accountName;
            $account->bank_details = [
                'bank_name' => $bankName,
                'branch_name' => $branchName,
                'bank_code1' => $bankCode1,
                'bank_code2' => $bankCode2,
                'bank_code3' => $bankCode3,
            ];
            $account->save();
        }

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $errors = [];

        $employees = Employee::query()
            ->summary()
            ->get();

        $newRows = [];
        $newRecords = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'employee');
            $accountName = Arr::get($row, 'name');
            $number = Arr::get($row, 'number');
            $bankName = Arr::get($row, 'bank_name');
            $branchName = Arr::get($row, 'branch_name');
            $bankCode1 = Arr::get($row, 'bank_code1');
            $bankCode2 = Arr::get($row, 'bank_code2');
            $bankCode3 = Arr::get($row, 'bank_code3');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('employee.props.name'), 'required');
            } elseif (! $employees->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('employee.props.name'), 'invalid');
            }

            if (! $accountName) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.name'), 'required');
            } elseif ($accountName && strlen($accountName) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.name'), 'max', ['max' => 100]);
            }

            if (! $number) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.number'), 'required');
            } elseif ($number && strlen($number) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.number'), 'max', ['max' => 100]);
            }

            if (! $bankName) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.bank_name'), 'required');
            } elseif ($bankName && strlen($bankName) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.bank_name'), 'max', ['max' => 100]);
            }

            if (! $branchName) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.branch_name'), 'required');
            } elseif ($branchName && strlen($branchName) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.account.props.branch_name'), 'max', ['max' => 100]);
            }

            if ($bankCode1 && strlen($bankCode1) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.config.props.bank_code1'), 'max', ['max' => 100]);
            }

            if ($bankCode2 && strlen($bankCode2) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.config.props.bank_code2'), 'max', ['max' => 100]);
            }

            if ($bankCode3 && strlen($bankCode3) > 100) {
                $errors[] = $this->setError($rowNo, trans('finance.config.props.bank_code3'), 'max', ['max' => 100]);
            }

            $employee = $employees->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first();

            $row['employee_id'] = $employee?->id;
            $row['contact_id'] = $employee?->contact_id;
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
