<?php

namespace App\Services\Transport\Vehicle\Config;

use App\Enums\OptionType;
use App\Helpers\ListHelper;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\ExpenseRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseTypeService
{
    public function preRequisite(Request $request): array
    {
        $colors = ListHelper::getListKey('colors');

        return compact('colors');
    }

    public function create(Request $request): Option
    {
        \DB::beginTransaction();

        $expenseType = Option::forceCreate($this->formatParams($request));

        \DB::commit();

        return $expenseType;
    }

    private function formatParams(Request $request, ?Option $expenseType = null): array
    {
        $formatted = [
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => OptionType::VEHICLE_EXPENSE_TYPE->value,
            'description' => $request->description,
        ];

        $formatted['meta']['color'] = $request->color ?? '';
        $formatted['meta']['has_reminder'] = $request->boolean('has_reminder');
        $formatted['meta']['has_quantity'] = $request->boolean('has_quantity');

        $formatted['meta']['is_document_required'] = $request->boolean('is_document_required');

        if (! $expenseType) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Option $expenseType): void
    {
        \DB::beginTransaction();

        $expenseType->forceFill($this->formatParams($request, $expenseType))->save();

        \DB::commit();
    }

    public function deletable(Request $request, Option $expenseType): void
    {
        $existingExpenses = ExpenseRecord::query()
            ->where('type_id', $expenseType->id)
            ->exists();

        if ($existingExpenses) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('transport.vehicle.expense_type.expense_type'), 'dependency' => trans('transport.vehicle.expense_record.expense_record')])]);
        }
    }
}
