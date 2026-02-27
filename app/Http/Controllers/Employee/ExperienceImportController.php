<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\ExperienceImportService;
use Illuminate\Http\Request;

class ExperienceImportController extends Controller
{
    public function __invoke(Request $request, ExperienceImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('employee.experience.experience')]),
        ]);
    }
}
