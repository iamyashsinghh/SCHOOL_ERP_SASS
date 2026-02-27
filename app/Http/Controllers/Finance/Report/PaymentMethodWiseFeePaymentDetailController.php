<?php

namespace App\Http\Controllers\Finance\Report;

use App\Http\Controllers\Controller;
use App\Services\Finance\Report\PaymentMethodWiseFeePaymentDetailListService;
use App\Services\Finance\Report\PaymentMethodWiseFeePaymentDetailService;
use Illuminate\Http\Request;

class PaymentMethodWiseFeePaymentDetailController extends Controller
{
    public function preRequisite(Request $request, PaymentMethodWiseFeePaymentDetailService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function fetch(Request $request, PaymentMethodWiseFeePaymentDetailListService $service)
    {
        return $service->paginate($request);
    }
}
