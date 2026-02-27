<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Services\Guardian\GuardianImportService;
use Illuminate\Http\Request;

class GuardianImportController extends Controller
{
    public function __invoke(Request $request, GuardianImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('guardian.guardian')]),
        ]);
    }
}
