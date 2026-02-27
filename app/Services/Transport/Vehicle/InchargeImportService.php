<?php

namespace App\Services\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Imports\Transport\Vehicle\InchargeImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InchargeImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('vehicle_incharge');

        $this->validateFile($request);

        Excel::import(new InchargeImport, $request->file('file'));

        $this->reportError('vehicle_incharge');
    }
}
