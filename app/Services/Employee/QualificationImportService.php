<?php

namespace App\Services\Employee;

use App\Concerns\ItemImport;
use App\Imports\Employee\QualificationImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QualificationImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('employee_qualification');

        $this->validateFile($request);

        Excel::import(new QualificationImport, $request->file('file'));

        $this->reportError('employee_qualification');
    }
}
