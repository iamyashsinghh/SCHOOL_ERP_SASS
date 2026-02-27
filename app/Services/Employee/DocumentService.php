<?php

namespace App\Services\Employee;

use App\Enums\OptionType;
use App\Enums\VerificationStatus;
use App\Http\Resources\Employee\Config\DocumentTypeResource;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Employee\Employee;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function preRequisite(Request $request): array
    {
        $types = DocumentTypeResource::collection(Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
            ->get());

        return compact('types');
    }

    public function findByUuidOrFail(Employee $employee, string $uuid): Document
    {
        return Document::query()
            ->whereHasMorph(
                'documentable',
                [Contact::class],
                function ($q) use ($employee) {
                    $q->whereId($employee->contact_id);
                }
            )
            ->whereUuid($uuid)
            ->getOrFail(trans('employee.document.document'));
    }

    public function create(Request $request, Employee $employee): Document
    {
        \DB::beginTransaction();

        $document = Document::forceCreate($this->formatParams($request, $employee));

        $employee->contact->documents()->save($document);

        if ($employee->user_id == auth()->id()) {
            $document->setMeta(['self_upload' => true]);
            $document->save();
        }

        $document->addMedia($request);

        \DB::commit();

        return $document;
    }

    private function formatParams(Request $request, Employee $employee, ?Document $document = null): array
    {
        $formatted = [
            'type_id' => $request->type_id,
            'title' => $request->title,
            'number' => $request->number,
            'issue_date' => $request->issue_date,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?: null,
            'description' => $request->description,
        ];

        $meta = $document?->meta ?? [];

        $meta['is_submitted_original'] = $request->boolean('is_submitted_original');

        $formatted['meta'] = $meta;

        if (! $document) {
            //
        }

        return $formatted;
    }

    private function isEditable(Employee $employee, Document $document): void
    {
        if (! $document->getMeta('self_upload')) {
            if ($employee->user_id == auth()->id()) {
                throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
            }

            return;
        }

        if ($employee->user_id != auth()->id()) {
            throw ValidationException::withMessages(['message' => trans('employee.could_not_edit_self_service_upload')]);
        }

        if ($document->getMeta('status') == VerificationStatus::REJECTED->value) {
            // throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
            // let them edit if the document is rejected
            return;
        }

        if (empty($document->verified_at->value)) {
            return;
        }

        throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
    }

    public function update(Request $request, Employee $employee, Document $document): void
    {
        $this->isEditable($employee, $document);

        \DB::beginTransaction();

        $document->forceFill($this->formatParams($request, $employee, $document))->save();

        $document->updateMedia($request);

        if ($document->getMeta('status') == VerificationStatus::REJECTED->value) {
            $document->setMeta([
                'status' => null,
                'comment' => null,
            ]);
            $document->save();
        }

        \DB::commit();
    }

    public function deletable(Employee $employee, Document $document): void
    {
        $this->isEditable($employee, $document);
    }
}
