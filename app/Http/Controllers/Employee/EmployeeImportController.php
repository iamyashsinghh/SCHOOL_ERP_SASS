<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeeImportService;
use Illuminate\Http\Request;

class EmployeeImportController extends Controller
{
    public function __invoke(Request $request, EmployeeImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        $action = 'global.imported';

        if (request()->input('action', 'create') != 'create') {
            $action = 'global.updated';
        }

        return response()->success([
            'imported' => true,
            'message' => trans($action, ['attribute' => trans('employee.employee')]),
        ]);
    }
}
