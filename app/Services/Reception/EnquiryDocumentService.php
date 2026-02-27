<?php

namespace App\Services\Reception;

use App\Enums\OptionType;
use App\Http\Resources\Reception\EnquiryDocumentResource;
use App\Http\Resources\Student\Config\DocumentTypeResource;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Option;
use App\Models\Reception\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnquiryDocumentService
{
    public function preRequisite(Request $request): array
    {
        $types = DocumentTypeResource::collection(Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::STUDENT_DOCUMENT_TYPE])
            ->get());

        return compact('types');
    }

    public function findByUuidOrFail(Enquiry $enquiry, string $uuid): Document
    {
        return Document::query()
            ->whereHasMorph(
                'documentable',
                [Contact::class],
                function ($q) use ($enquiry) {
                    $q->whereId($enquiry->contact_id);
                }
            )
            ->whereUuid($uuid)
            ->getOrFail(trans('student.document.document'));
    }

    public function list(Request $request, Enquiry $enquiry): AnonymousResourceCollection
    {
        return EnquiryDocumentResource::collection($enquiry->contact->documents()->get());
    }

    public function create(Request $request, Enquiry $enquiry): Document
    {
        \DB::beginTransaction();

        $document = Document::forceCreate($this->formatParams($request, $enquiry));

        $enquiry->contact->documents()->save($document);

        $document->addMedia($request);

        \DB::commit();

        return $document;
    }

    private function formatParams(Request $request, Enquiry $enquiry, ?Document $document = null): array
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

        if (! $document) {
            //
        }

        $meta = $document?->meta ?? [];

        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Enquiry $enquiry, Document $document): void
    {
        \DB::beginTransaction();

        $document->forceFill($this->formatParams($request, $enquiry, $document))->save();

        $document->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Enquiry $enquiry, Document $document): void
    {
        //
    }
}
