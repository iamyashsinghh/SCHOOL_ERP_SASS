<?php

namespace App\Services\Guardian;

use App\Concerns\ItemImport;
use App\Imports\Guardian\GuardianImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class GuardianImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('guardian');

        $this->validateFile($request);

        Excel::import(new GuardianImport, $request->file('file'));

        $this->reportError('guardian');
    }
}
