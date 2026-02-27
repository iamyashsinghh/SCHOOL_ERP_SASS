<?php

namespace App\Services\Academic;

use App\Concerns\ItemImport;
use App\Imports\Academic\BookListImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BookListImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('book_list');

        $this->validateFile($request);

        Excel::import(new BookListImport, $request->file('file'));

        $this->reportError('book_list');
    }
}
