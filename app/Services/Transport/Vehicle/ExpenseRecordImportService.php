<?php

namespace App\Services\Transport\Vehicle;

use App\Concerns\ItemImport;
use App\Imports\Transport\Vehicle\ExpenseRecordImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseRecordImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('vehicle_expense_record');

        $this->validateFile($request);

        Excel::import(new ExpenseRecordImport, $request->file('file'));

        $this->reportError('vehicle_expense_record');
    }
}
