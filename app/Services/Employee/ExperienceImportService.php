<?php

namespace App\Services\Employee;

use App\Concerns\ItemImport;
use App\Imports\Employee\ExperienceImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExperienceImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('employee_experience');

        $this->validateFile($request);

        Excel::import(new ExperienceImport, $request->file('file'));

        $this->reportError('employee_experience');
    }
}
