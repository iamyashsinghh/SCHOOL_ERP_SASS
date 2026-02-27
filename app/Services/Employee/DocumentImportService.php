<?php

namespace App\Services\Employee;

use App\Concerns\ItemImport;
use App\Imports\Employee\DocumentImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DocumentImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('employee_document');

        $this->validateFile($request);

        Excel::import(new DocumentImport, $request->file('file'));

        $this->reportError('employee_document');
    }
}
