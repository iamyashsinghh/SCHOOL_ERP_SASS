<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\TransactionImportService;
use Illuminate\Http\Request;

class TransactionImportController extends Controller
{
    public function __invoke(Request $request, TransactionImportService $service)
    {
        $service->import($request);

        if (request()->boolean('validate')) {
            return response()->success([
                'message' => trans('general.data_validated'),
            ]);
        }

        return response()->success([
            'imported' => true,
            'message' => trans('global.imported', ['attribute' => trans('finance.transaction.transaction')]),
        ]);
    }
}
