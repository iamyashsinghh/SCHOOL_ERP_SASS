<?php

namespace App\Services\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Imports\Transport\Vehicle\VehicleImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VehicleImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('vehicle');

        $this->validateFile($request);

        Excel::import(new VehicleImport, $request->file('file'));

        $this->reportError('vehicle');
    }
}
