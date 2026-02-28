<?php

namespace App\Services\Student;

use App\Actions\Finance\GetPaymentGateway;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Student\Registration;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\TempStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AnonymousPaymentService
{
    public function getDetail(Request $request)
    {
        $tempStorage = TempStorage::query()
            ->whereUuid($request->uuid)
            ->firstOrFail();

        if (app()->environment('production') && ! $tempStorage->getMeta('is_system_generated') && $tempStorage->created_at->diffInMinutes(now()) > config('config.student.payment_link_qr_code_expiry_duration', 10)) {
            return abort(398, trans('student.payment.link_expired'));
        }

        if (!in_array($tempStorage->type, ['student_fee_payment', 'registration_fee_payment'])) {
            return abort(398, trans('general.errors.invalid_action'));
        }

        if ($tempStorage->type == 'registration_fee_payment') {
            return $this->getRegistrationFeePaymentDetail($tempStorage);
        }

        $student = Student::query()
            ->whereUuid($tempStorage->getValue('student'))
            ->firstOrFail();

        $amount = (float) $tempStorage->getValue('amount');

        if (! is_numeric($amount) || $amount <= 0) {
            return abort(398, trans('general.errors.invalid_input'));
        }

        $transaction = Transaction::query()
            ->where('meta->temp_payment_uuid', $tempStorage->uuid)
            ->where('is_online', true)
            ->whereNotNull('processed_at')
            ->first();

        $amount = \Price::from($amount);

        $student->load('admission', 'contact', 'batch.course', 'period.team');

        $paymentGateways = (new GetPaymentGateway)->execute($student->period?->team_id);

        $duration = (int) config('config.student.payment_link_qr_code_expiry_duration', 10);
        $createdAt = $tempStorage->getMeta('is_system_generated') ? now() : $tempStorage->created_at;
        $expiryDate = $createdAt->addMinutes($duration)->toDateTimeString();

        $isExpired = $expiryDate < now()->toDateTimeString();

        $feeInstallments = $tempStorage->getValue('fee_installments') ?: [];

        if ($tempStorage->getValue('fee_group')) {
            $feeInstallments = Fee::query()
                ->select('uuid')
                ->whereHas('installment', function ($query) use ($tempStorage) {
                    $query->whereHas('group', function ($query) use ($tempStorage) {
                        $query->where('uuid', $tempStorage->getValue('fee_group'));
                    });
                })
                ->get()
                ->pluck('uuid')
                ->all();
        }

        $date = today()->toDateString();

        $detail = [];
        if ($tempStorage->getValue('fee_installment')) {
            $feeInstallments = Fee::query()
                ->with('installment')
                ->whereHas('student', function ($query) use ($student) {
                    $query->where('uuid', $student->uuid);
                })
                ->where('uuid', $tempStorage->getValue('fee_installment'))
                ->get();

            $detail = [
                'type' => 'installments',
                'installments' => $feeInstallments->map(function ($fee) use ($date) {
                    $lateFee = $fee->calculateLateFeeAmount($date)->value;
                    $total = $fee->total->value + $lateFee;

                    return [
                        'uuid' => $fee->uuid,
                        'title' => $fee->installment->title,
                        'due_date' => $fee->due_date->value ? $fee->due_date : $fee->installment->due_date,
                        'late_fee' => \Price::from($lateFee),
                        'total' => \Price::from($total),
                        'paid' => $fee->paid,
                        'balance' => \Price::from($total - $fee->paid->value),
                    ];
                }),
            ];
        } elseif (count($feeInstallments) > 0) {
            $feeInstallments = Fee::query()
                ->with('installment')
                ->whereHas('student', function ($query) use ($student) {
                    $query->where('uuid', $student->uuid);
                })
                ->whereIn('uuid', $feeInstallments)
                ->get();

            $detail = [
                'type' => 'installments',
                'installments' => $feeInstallments->map(function ($fee) use ($date) {
                    $lateFee = $fee->calculateLateFeeAmount($date)->value;
                    $total = $fee->total->value + $lateFee;

                    return [
                        'uuid' => $fee->uuid,
                        'title' => $fee->installment->title,
                        'due_date' => $fee->due_date->value ? $fee->due_date : $fee->installment->due_date,
                        'late_fee' => \Price::from($lateFee),
                        'total' => \Price::from($total),
                        'paid' => $fee->paid,
                        'balance' => \Price::from($total - $fee->paid->value),
                    ];
                }),
            ];

            $feeInstallments = $feeInstallments
                ->map(function ($fee) {
                    $balance = $fee->total->value - $fee->paid->value;
                    $lateFee = $fee->calculateLateFeeAmount()->value;
                    $balance += $lateFee;

                    return [
                        'uuid' => $fee->uuid,
                        'balance' => \Price::from($balance),
                        'late_fee' => \Price::from($lateFee),
                        'installment' => $fee->installment->title,
                    ];
                });

            if (!$transaction?->processed_at?->value && $feeInstallments->sum('balance.value') != $amount->value) {
                return abort(398, trans('general.errors.invalid_input'));
            }
        }

        return [
            'type' => 'student_fee_payment',
            'student' => [
                'uuid' => $student->uuid,
                'code_number' => $student->admission->code_number,
                'name' => $student->contact->name,
                'batch_name' => $student->batch->name,
                'course_name' => $student->batch->course->name,
                'period_name' => $student->period->name,
            ],
            'transaction' => [
                'has_completed' => $transaction ? true : false,
                'reference_number' => Arr::get($transaction?->payment_gateway, 'reference_number'),
            ],
            'date' => \Cal::date(today()->format('d-m-Y')),
            'amount' => $amount,
            'fee_group' => [
                'uuid' => $tempStorage->getValue('fee_group'),
                'balance' => $amount,
            ],
            'fee_head' => [
                'uuid' => $tempStorage->getValue('fee_head'),
                'balance' => $amount,
            ],
            'fee_installment' => [
                'uuid' => $tempStorage->getValue('fee_installment'),
                'balance' => $amount,
            ],
            'fee_installments' => $feeInstallments,
            'show_detail' => true,
            'detail' => $detail,
            'payment_gateways' => $paymentGateways,
            'expiry_date' => \Cal::dateTime($expiryDate),
            'is_expired' => $isExpired,
        ];
    }

    private function getRegistrationFeePaymentDetail(TempStorage $tempStorage)
    {
        $registration = Registration::query()
            ->with('transactions')
            ->whereUuid($tempStorage->getValue('registration'))
            ->firstOrFail();

        $paid = $registration->transactions->filter(function ($transaction) {
            return empty($transaction->cancelled_at->value) && empty($transaction->rejected_at->value) && (
                ! $transaction->is_online || ($transaction->is_online && ! empty($transaction->processed_at->value))
            );
        })->sum('amount.value');

        $balance = $registration->fee->value - $paid;

        $amount = (float) $tempStorage->getValue('amount');

        if (! is_numeric($amount) || $amount <= 0) {
            return abort(398, trans('general.errors.invalid_input'));
        }

        $transaction = Transaction::query()
            ->where('meta->temp_payment_uuid', $tempStorage->uuid)
            ->where('is_online', true)
            ->whereNotNull('processed_at')
            ->first();

        $amount = \Price::from($amount);

        $registration->load('contact', 'course', 'period.team');

        $paymentGateways = (new GetPaymentGateway)->execute($registration->period?->team_id);

        $duration = (int) config('config.student.payment_link_qr_code_expiry_duration', 10);
        $createdAt = $tempStorage->getMeta('is_system_generated') ? now() : $tempStorage->created_at;
        $expiryDate = $createdAt->addMinutes($duration)->toDateTimeString();

        $isExpired = $expiryDate < now()->toDateTimeString();

        return [
            'type' => 'registration_fee_payment',
            'registration' => [
                'uuid' => $registration->uuid,
                'code_number' => $registration->code_number,
                'name' => $registration->contact->name,
                'course_name' => $registration->course->name,
                'period_name' => $registration->period->name,
                'balance' => \Price::from($balance),
            ],
            'transaction' => [
                'has_completed' => $transaction ? true : false,
                'reference_number' => Arr::get($transaction?->payment_gateway, 'reference_number'),
            ],
            'date' => \Cal::date(today()->format('d-m-Y')),
            'amount' => $amount,
            'show_detail' => true,
            'payment_gateways' => $paymentGateways,
            'expiry_date' => \Cal::dateTime($expiryDate),
            'is_expired' => $isExpired,
        ];
    }
}
