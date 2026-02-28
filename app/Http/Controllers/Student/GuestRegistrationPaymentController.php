<?php

namespace App\Http\Controllers\Student;

use App\Contracts\Finance\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Student\Registration;
use App\Services\Student\GuestRegistrationPaymentService;
use App\Services\Student\OnlineRegistrationPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class GuestRegistrationPaymentController extends Controller
{
    public function preRequisite(GuestRegistrationPaymentService $service)
    {
        return $service->preRequisite();
    }

    public function initiate(Request $request, string $registration, OnlineRegistrationPaymentService $service, PaymentGateway $paymentGateway)
    {
        $registration = Registration::findWithoutPeriodByUuidOrFail($registration);

        $service->setFinanceConfig($registration->period->team_id);

        return response()->success($service->initiate($request, $registration, $paymentGateway));
    }

    public function complete(Request $request, string $registration, OnlineRegistrationPaymentService $service, PaymentGateway $paymentGateway)
    {
        $registration = Registration::findWithoutPeriodByUuidOrFail($registration);

        $service->setFinanceConfig($registration->period->team_id);

        $transaction = $service->makePayment($request, $registration, $paymentGateway);

        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');
        $amount = $transaction->amount->formatted;

        return response()->success([
            'message' => trans('student.fee.paid_online', ['reference' => $referenceNumber, 'amount' => $amount]),
        ]);
    }

    public function fail(Request $request, string $registration, OnlineRegistrationPaymentService $service, PaymentGateway $paymentGateway)
    {
        $registration = Registration::findWithoutPeriodByUuidOrFail($registration);

        $service->setFinanceConfig($registration->period->team_id);

        $transaction = $service->failPayment($request, $registration, $paymentGateway);

        return response()->success([
            'message' => trans('global.failed', ['attribute' => trans('student.payment.payment')]),
        ]);
    }
}
