<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\VehicleImportService;
use Illuminate\Http\Request;

class VehicleImportController extends Controller
{
    public function __invoke(Request $request, VehicleImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('transport.vehicle.vehicle')]),
        ]);
    }
}
