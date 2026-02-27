<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\HubtelService;
use Illuminate\Http\Request;

class HubtelController extends Controller
{
    public function checkStatus(Request $request, HubtelService $service)
    {
        return $service->checkStatus($request);
    }

    public function callback(Request $request, HubtelService $service)
    {
        return $service->callback($request);
    }

    public function getResponse(Request $request, HubtelService $service)
    {
        return $service->getResponse($request);
    }
}
