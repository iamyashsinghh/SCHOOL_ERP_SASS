<?php

namespace App\Services\Inventory;

use App\Concerns\ItemImport;
use App\Imports\Inventory\VendorImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VendorImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('vendor');

        $this->validateFile($request);

        Excel::import(new VendorImport, $request->file('file'));

        $this->reportError('vendor');
    }
}
