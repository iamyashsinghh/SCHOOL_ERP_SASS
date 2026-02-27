<?php

namespace App\Services\Finance;

use App\Models\Finance\Tax;
use Illuminate\Http\Request;

class TaxService
{
    public function preRequisite(): array
    {
        return [];
    }

    public function create(Request $request): Tax
    {
        \DB::beginTransaction();

        $tax = Tax::forceCreate($this->formatParams($request));

        \DB::commit();

        return $tax;
    }

    private function formatParams(Request $request, ?Tax $tax = null): array
    {
        $formatted = [
            'name' => $request->name,
            'code' => $request->code,
            'rate' => $request->rate,
            'components' => $request->components,
            'description' => $request->description,
        ];

        if (! $tax) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function isEditable(Tax $tax) {}

    public function update(Tax $tax, Request $request): void
    {
        $this->isEditable($tax);

        \DB::beginTransaction();

        $tax->forceFill($this->formatParams($request, $tax))->save();

        \DB::commit();
    }

    public function deletable(Tax $tax): bool
    {
        $this->isEditable($tax);

        return true;
    }
}
