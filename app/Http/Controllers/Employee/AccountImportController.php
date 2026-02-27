<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\AccountImportService;
use Illuminate\Http\Request;

class AccountImportController extends Controller
{
    public function __invoke(Request $request, AccountImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('employee.account.account')]),
        ]);
    }
}
