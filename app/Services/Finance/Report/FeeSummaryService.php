<?php

namespace App\Services\Finance\Report;

use App\Enums\OptionType;
use App\Enums\Student\StudentStatus;
use App\Http\Resources\Finance\FeeGroupResource;
use App\Http\Resources\Finance\FeeStructureResource;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Finance\FeeGroup;
use App\Models\Tenant\Finance\FeeStructure;
use App\Models\Tenant\Option;

class FeeSummaryService
{
    public function preRequisite(): array
    {
        $statuses = StudentStatus::getOptions();

        $feeStructures = FeeStructureResource::collection(FeeStructure::query()
            ->byPeriod()
            ->get()
        );

        $feeGroups = FeeGroupResource::collection(FeeGroup::query()
            ->byPeriod()
            ->get());

        $categories = config('config.contact.enable_category_field') ? OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::MEMBER_CATEGORY->value)
            ->get()) : [];

        return compact('statuses', 'feeStructures', 'feeGroups', 'categories');
    }
}
