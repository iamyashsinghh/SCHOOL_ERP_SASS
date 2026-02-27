<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentImportService;
use Illuminate\Http\Request;

class StudentImportController extends Controller
{
    public function __invoke(Request $request, StudentImportService $service)
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
            'message' => trans($action, ['attribute' => trans('student.student')]),
        ]);
    }
}
