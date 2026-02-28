<?php

namespace App\Services\Employee;

use App\Concerns\SubordinateAccess;
use App\Enums\DocumentExpiryStatus;
use App\Enums\OptionType;
use App\Enums\VerificationStatus;
use App\Http\Resources\Employee\Config\DocumentTypeResource;
use App\Models\Tenant\Document;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentsService
{
    use SubordinateAccess;

    public function preRequisite(Request $request): array
    {
        $types = DocumentTypeResource::collection(Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
            ->get());

        $statuses = DocumentExpiryStatus::getOptions();

        return compact('types', 'statuses');
    }

    public function findByUuidOrFail(string $uuid): Document
    {
        $document = Document::query()
            ->select('documents.*')
            ->selectRaw('DATEDIFF(end_date, CURDATE()) as expiry_in_days')
            ->whereUuid($uuid)
            ->getOrFail(trans('employee.document.document'));

        return $document;
    }

    public function findEmployee(Document $document): Employee
    {
        return Employee::query()
            ->summary()
            ->filterAccessible()
            ->where('contact_id', $document->documentable_id)
            ->getOrFail(trans('employee.employee'));
    }

    public function create(Request $request): Document
    {
        \DB::beginTransaction();

        $document = Document::forceCreate($this->formatParams($request));

        $document->addMedia($request);

        \DB::commit();

        return $document;
    }

    private function formatParams(Request $request, ?Document $document = null): array
    {
        $formatted = [
            'type_id' => $request->type_id,
            'documentable_type' => 'Contact',
            'documentable_id' => $request->contact_id,
            'title' => $request->title,
            'number' => $request->number,
            'issue_date' => $request->issue_date,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
        ];

        $meta = $document?->meta ?? [];

        $meta['is_submitted_original'] = $request->boolean('is_submitted_original');

        if ($request->user_id == auth()->id()) {
            $meta['self_upload'] = true;
            $formatted['verified_at'] = null;
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function isEditable(Request $request, Document $document): void
    {
        if (! $document->getMeta('self_upload')) {
            if ($request->user_id == auth()->id()) {
                throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
            }

            return;
        }

        if ($request->user_id != auth()->id()) {
            throw ValidationException::withMessages(['message' => trans('employee.could_not_edit_self_service_upload')]);
        }

        if ($document->getMeta('status') == VerificationStatus::REJECTED->value) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        if (empty($document->verified_at->value)) {
            return;
        }

        throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
    }

    public function update(Request $request, Document $document): void
    {
        $this->isEditable($request, $document);

        \DB::beginTransaction();

        $document->forceFill($this->formatParams($request, $document))->save();

        $document->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Request $request, Document $document): void
    {
        $this->isEditable($request, $document);
    }
}
