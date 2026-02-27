<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\InchargeImportService;
use Illuminate\Http\Request;

class InchargeImportController extends Controller
{
    public function __invoke(Request $request, InchargeImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('transport.vehicle.incharge.incharge')]),
        ]);
    }
}
