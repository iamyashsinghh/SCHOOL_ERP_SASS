<?php

namespace App\Services\Academic;

use App\Concerns\ItemImport;
use App\Imports\Academic\SubjectInchargeImport;
use App\Imports\Academic\SubjectInchargeWithCodeImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SubjectInchargeImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('subject_incharge');

        $this->validateFile($request);

        if ($request->boolean('withEmployeeCode')) {
            Excel::import(new SubjectInchargeWithCodeImport, $request->file('file'));
        } else {
            Excel::import(new SubjectInchargeImport, $request->file('file'));
        }

        $this->reportError('subject_incharge');
    }
}
