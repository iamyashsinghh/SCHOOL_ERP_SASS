<?php

namespace App\Services\Finance\Report;

use App\Enums\Finance\TransactionStatus;
use App\Enums\OptionType;
use App\Http\Resources\Academic\PeriodResource;
use App\Http\Resources\Finance\PaymentMethodResource;
use App\Http\Resources\OptionResource;
use App\Models\Academic\Period;
use App\Models\Finance\PaymentMethod;
use App\Models\Option;

class FeePaymentService
{
    public function preRequisite(): array
    {
        $statuses = TransactionStatus::getOptions();

        $paymentMethods = PaymentMethodResource::collection(PaymentMethod::query()
            ->byTeam()
            // ->where('is_payment_gateway', false)
            ->get());

        $periods = PeriodResource::collection(Period::query()
            ->with('session')
            ->byTeam()
            ->get());

        $categories = config('config.contact.enable_category_field') ?  OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::MEMBER_CATEGORY->value)
            ->get()) : [];

        return compact('statuses', 'paymentMethods', 'periods', 'categories');
    }
}
