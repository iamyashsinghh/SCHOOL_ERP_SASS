<?php

namespace App\Services\Student;

use App\Concerns\ItemImport;
use App\Imports\Student\DocumentImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DocumentImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('student_document');

        $this->validateFile($request);

        Excel::import(new DocumentImport, $request->file('file'));

        $this->reportError('student_document');
    }
}
