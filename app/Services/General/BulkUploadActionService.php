<?php

namespace App\Services\General;

use App\Concerns\ItemImport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class BulkUploadActionService
{
    use ItemImport;

    public function preRequisite()
    {
        $actions = $this->getActions();

        return compact('actions');
    }

    public function getActions()
    {
        return [
            ['label' => 'Fix Fee Import Date', 'value' => 'fix_fee_import_date'],
        ];
    }

    public function import(Request $request)
    {
        $filename = match ($request->input('action')) {
            'fix_fee_import_date' => 'fix_fee_import_date',
            default => 'bulk_upload_action',
        };

        $this->deleteLogFile($filename);

        $this->validateFile($request);

        if ($request->input('action') == 'fix_fee_import_date') {
            // Excel::import(new BulkImport, $request->file('file'));
        } else {
            throw ValidationException::withMessages(['action' => trans('general.errors.invalid_action')]);
        }

        $this->reportError($filename);
    }
}
