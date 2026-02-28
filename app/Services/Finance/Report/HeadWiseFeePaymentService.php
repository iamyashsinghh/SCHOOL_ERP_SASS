<?php

namespace App\Services\Finance\Report;

use App\Http\Resources\Academic\PeriodResource;
use App\Http\Resources\Finance\LedgerResource;
use App\Http\Resources\Finance\PaymentMethodResource;
use App\Models\Tenant\Academic\Period;
use App\Models\Tenant\Finance\Ledger;
use App\Models\Tenant\Finance\PaymentMethod;

class HeadWiseFeePaymentService
{
    public function preRequisite(): array
    {
        $paymentMethods = PaymentMethodResource::collection(PaymentMethod::query()
            ->byTeam()
            // ->where('is_payment_gateway', false)
            ->get());

        $ledgers = LedgerResource::collection(Ledger::query()
            ->byTeam()
            ->subType('primary')
            ->get()
        );

        $periods = PeriodResource::collection(Period::query()
            ->with('session')
            ->byTeam()
            ->get());

        return compact('paymentMethods', 'ledgers', 'periods');
    }
}
