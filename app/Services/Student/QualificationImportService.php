<?php

namespace App\Services\Student;

use App\Concerns\ItemImport;
use App\Imports\Student\QualificationImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QualificationImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('student_qualification');

        $this->validateFile($request);

        Excel::import(new QualificationImport, $request->file('file'));

        $this->reportError('student_qualification');
    }
}
