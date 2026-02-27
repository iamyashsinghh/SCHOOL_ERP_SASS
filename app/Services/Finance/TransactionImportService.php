<?php

namespace App\Services\Finance;

use App\Concerns\ItemImport;
use App\Imports\Finance\TransactionImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TransactionImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('transaction');

        $this->validateFile($request);

        Excel::import(new TransactionImport, $request->file('file'));

        $this->reportError('transaction');
    }
}
