<?php

namespace App\Services\Finance\Report;

use App\Enums\OptionType;
use App\Enums\Student\StudentStatus;
use App\Http\Resources\Finance\FeeGroupResource;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Finance\FeeGroup;
use App\Models\Tenant\Option;

class FeeDueService
{
    public function preRequisite(): array
    {
        $statuses = StudentStatus::getOptions();

        $feeGroups = FeeGroupResource::collection(FeeGroup::query()
            ->byPeriod()
            ->get());

        $categories = config('config.contact.enable_category_field') ?  OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::MEMBER_CATEGORY->value)
            ->get()) : [];

        return compact('statuses', 'feeGroups', 'categories');
    }
}
