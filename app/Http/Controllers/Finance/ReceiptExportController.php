<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\ReceiptListService;
use Illuminate\Http\Request;

class ReceiptExportController extends Controller
{
    public function __invoke(Request $request, ReceiptListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
