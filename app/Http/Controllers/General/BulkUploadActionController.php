<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Services\General\BulkUploadActionService;
use Illuminate\Http\Request;

class BulkUploadActionController extends Controller
{
    public function preRequisite(Request $request, BulkUploadActionService $service)
    {
        return $service->preRequisite();
    }

    public function import(Request $request, BulkUploadActionService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => 'data']),
        ]);
    }
}
