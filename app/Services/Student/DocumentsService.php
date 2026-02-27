<?php

namespace App\Services\Student;

use App\Enums\OptionType;
use App\Enums\VerificationStatus;
use App\Http\Resources\Student\Config\DocumentTypeResource;
use App\Models\Document;
use App\Models\Option;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentsService
{
    public function preRequisite(Request $request): array
    {
        $types = DocumentTypeResource::collection(Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::STUDENT_DOCUMENT_TYPE])
            ->get());

        return compact('types');
    }

    public function findByUuidOrFail(string $uuid): Document
    {
        $document = Document::query()
            ->select('documents.*')
            ->selectRaw('DATEDIFF(end_date, CURDATE()) as expiry_in_days')
            ->whereUuid($uuid)
            ->getOrFail(trans('student.document.document'));

        return $document;
    }

    public function findStudent(Document $document): Student
    {
        return Student::query()
            ->summary()
            ->byPeriod()
            ->filterAccessible()
            ->where('contact_id', $document->documentable_id)
            ->getOrFail(trans('student.student'));
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
            throw ValidationException::withMessages(['message' => trans('student.could_not_edit_self_service_upload')]);
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
