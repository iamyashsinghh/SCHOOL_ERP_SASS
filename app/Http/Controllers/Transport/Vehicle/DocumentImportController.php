<?php

namespace App\Http\Controllers\Transport\Vehicle;

use App\Http\Controllers\Controller;
use App\Services\Transport\Vehicle\DocumentImportService;
use Illuminate\Http\Request;

class DocumentImportController extends Controller
{
    public function __invoke(Request $request, DocumentImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('transport.vehicle.document.document')]),
        ]);
    }
}
