<?php

namespace App\Services\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Imports\Transport\Vehicle\DocumentImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DocumentImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('vehicle_document');

        $this->validateFile($request);

        Excel::import(new DocumentImport, $request->file('file'));

        $this->reportError('vehicle_document');
    }
}
