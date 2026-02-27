<?php

namespace App\Http\Controllers\Finance\Report;

use App\Http\Controllers\Controller;
use App\Services\Finance\Report\PaymentMethodWiseFeePaymentDetailListService;
use Illuminate\Http\Request;

class PaymentMethodWiseFeePaymentDetailExportController extends Controller
{
    public function __invoke(Request $request, PaymentMethodWiseFeePaymentDetailListService $service)
    {
        $startDate = \Cal::date($request->query('startDate', today()->subWeek(1)->toDateString()))->formatted;
        $endDate = \Cal::date($request->query('endDate', today()->toDateString()))->formatted;
        $rows = $service->list($request);

        return view('print.finance.report.payment-method-wise-fee-payment-detail', compact('rows', 'startDate', 'endDate'));
    }
}
