<?php

namespace App\Services\Contact\Config;

use App\Enums\OptionType;
use App\Helpers\ListHelper;
use App\Models\Tenant\Document;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentTypeService
{
    public function preRequisite(Request $request): array
    {
        $colors = ListHelper::getListKey('colors');

        return compact('colors');
    }

    public function create(Request $request): Option
    {
        \DB::beginTransaction();

        $documentType = Option::forceCreate($this->formatParams($request));

        \DB::commit();

        return $documentType;
    }

    private function formatParams(Request $request, ?Option $documentType = null): array
    {
        $formatted = [
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => OptionType::DOCUMENT_TYPE->value,
            'description' => $request->description,
        ];

        $formatted['meta']['color'] = $request->color ?? '';
        if ($request->has_expiry_date) {
            $formatted['meta']['has_expiry_date'] = $request->boolean('has_expiry_date');
            $formatted['meta']['alert_days_before_expiry'] = $request->alert_days_before_expiry;
        } else {
            $formatted['meta']['has_expiry_date'] = false;
            $formatted['meta']['alert_days_before_expiry'] = 0;
        }

        if ($request->has_number) {
            $formatted['meta']['has_number'] = $request->boolean('has_number');
            $formatted['meta']['number_format'] = $request->number_format;
        } else {
            $formatted['meta']['has_number'] = false;
            $formatted['meta']['number_format'] = '';
        }

        $formatted['meta']['is_document_required'] = $request->boolean('is_document_required');

        if (! $documentType) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Option $documentType): void
    {
        \DB::beginTransaction();

        $documentType->forceFill($this->formatParams($request, $documentType))->save();

        \DB::commit();
    }

    public function deletable(Request $request, Option $documentType): void
    {
        $existingDocuments = Document::query()
            ->where('type_id', $documentType->id)
            ->exists();

        if ($existingDocuments) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('contact.document_type.document_type'), 'dependency' => trans('contact.document.document')])]);
        }
    }
}
