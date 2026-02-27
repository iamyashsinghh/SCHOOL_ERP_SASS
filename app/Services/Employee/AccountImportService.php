<?php

namespace App\Services\Employee;

use App\Concerns\ItemImport;
use App\Imports\Employee\AccountImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AccountImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('employee_account');

        $this->validateFile($request);

        Excel::import(new AccountImport, $request->file('file'));

        $this->reportError('employee_account');
    }
}
