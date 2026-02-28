<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentFees;
use App\Contracts\Finance\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Jobs\Notifications\Student\SendFeePaymentConfirmedNotification;
use App\Jobs\Notifications\Student\SendFeePaymentFailedNotification;
use App\Models\Tenant\Student\Student;
use App\Services\Student\OnlinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OnlinePaymentController extends Controller
{
    public function initiate(Request $request, string $student, OnlinePaymentService $service, PaymentGateway $paymentGateway)
    {
        $student = Student::findSummaryByUuidOrFail($student);

        (new GetStudentFees)->validatePreviousDue($student);

        $this->authorize('makePayment', $student);

        return response()->success($service->initiate($request, $student, $paymentGateway));
    }

    public function complete(Request $request, string $student, OnlinePaymentService $service, PaymentGateway $paymentGateway)
    {
        $student = Student::findSummaryByUuidOrFail($student);

        $this->authorize('makePayment', $student);

        $transaction = $service->makePayment($request, $student, $paymentGateway);

        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');
        $amount = $transaction->amount->formatted;

        SendFeePaymentConfirmedNotification::dispatch([
            'student_id' => $student->id,
            'transaction_id' => $transaction->id,
            'team_id' => $student->team_id,
        ]);

        return response()->success([
            'message' => trans('student.fee.paid_online', ['reference' => $referenceNumber, 'amount' => $amount]),
        ]);
    }

    public function fail(Request $request, string $student, OnlinePaymentService $service, PaymentGateway $paymentGateway)
    {
        $student = Student::findSummaryByUuidOrFail($student);

        $this->authorize('makePayment', $student);

        $transaction = $service->failPayment($request, $student, $paymentGateway);

        SendFeePaymentFailedNotification::dispatch([
            'student_id' => $student->id,
            'transaction_id' => $transaction->id,
            'team_id' => $student->team_id,
        ]);

        return response()->success([
            'message' => trans('global.failed', ['attribute' => trans('student.payment.payment')]),
        ]);
    }

    public function updatePaymentStatus(Request $request, string $student, string $uuid, OnlinePaymentService $service)
    {
        $student = Student::findByUuidOrFail($student);

        $this->authorize('view', $student);

        $service->updatePaymentStatus($request, $student, $uuid);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.payment.payment')]),
        ]);
    }

    public function refreshSelfPayment(Request $request, string $student, string $uuid, OnlinePaymentService $service)
    {
        $student = Student::findByUuidOrFail($student);

        $this->authorize('view', $student);

        $service->refreshSelfPayment($request, $student, $uuid);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.payment.payment')]),
        ]);
    }
}
