<?php

namespace App\Services\Student;

use App\Enums\OptionType;
use App\Http\Resources\Student\Config\DocumentTypeResource;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Document;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;

class DocumentService
{
    public function preRequisite(Request $request): array
    {
        $types = DocumentTypeResource::collection(Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::STUDENT_DOCUMENT_TYPE])
            ->get());

        return compact('types');
    }

    public function findByUuidOrFail(Student $student, string $uuid): Document
    {
        return Document::query()
            ->whereHasMorph(
                'documentable',
                [Contact::class],
                function ($q) use ($student) {
                    $q->whereId($student->contact_id);
                }
            )
            ->whereUuid($uuid)
            ->getOrFail(trans('student.document.document'));
    }

    public function create(Request $request, Student $student): Document
    {
        \DB::beginTransaction();

        $document = Document::forceCreate($this->formatParams($request, $student));

        $student->contact->documents()->save($document);

        $document->addMedia($request);

        \DB::commit();

        return $document;
    }

    private function formatParams(Request $request, Student $student, ?Document $document = null): array
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

        return $formatted;
    }

    public function update(Request $request, Student $student, Document $document): void
    {
        \DB::beginTransaction();

        $document->forceFill($this->formatParams($request, $student, $document))->save();

        $document->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Student $student, Document $document): void
    {
        //
    }
}
