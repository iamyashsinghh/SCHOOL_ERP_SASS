<?php

namespace App\Services\Student;

use App\Concerns\ItemImport;
use App\Imports\Student\AccountImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AccountImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('student_account');

        $this->validateFile($request);

        Excel::import(new AccountImport, $request->file('file'));

        $this->reportError('student_account');
    }
}
