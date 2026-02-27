<?php

namespace App\Services\Student;

use App\Concerns\ItemImport;
use App\Imports\Student\StudentBulkUpdate;
use App\Imports\Student\StudentImport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportService
{
    use ItemImport;

    public function import(Request $request)
    {
        $this->deleteLogFile('student');

        $this->validateFile($request);

        if ($request->input('action', 'create') == 'bulk_update') {

            if (! auth()->user()->can('student:edit')) {
                throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
            }

            Excel::import(new StudentBulkUpdate, $request->file('file'));
        } else {
            Excel::import(new StudentImport, $request->file('file'));
        }

        $this->reportError('student');
    }
}
