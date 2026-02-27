<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reception\EnquiryDocumentRequest;
use App\Http\Resources\Reception\EnquiryDocumentResource;
use App\Models\Reception\Enquiry;
use App\Services\Reception\EnquiryDocumentService;
use Illuminate\Http\Request;

class EnquiryDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, string $enquiry, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('view', $enquiry);

        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, string $enquiry, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('view', $enquiry);

        return $service->list($request, $enquiry);
    }

    public function store(EnquiryDocumentRequest $request, string $enquiry, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('update', $enquiry);

        $document = $service->create($request, $enquiry);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('student.document.document')]),
            'document' => EnquiryDocumentResource::make($document),
        ]);
    }

    public function show(string $enquiry, string $document, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('view', $enquiry);

        $document = $service->findByUuidOrFail($enquiry, $document);

        $document->load('level', 'media');

        return EnquiryDocumentResource::make($document);
    }

    public function update(EnquiryDocumentRequest $request, string $enquiry, string $document, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('update', $enquiry);

        $document = $service->findByUuidOrFail($enquiry, $document);

        $service->update($request, $enquiry, $document);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.document.document')]),
        ]);
    }

    public function destroy(string $enquiry, string $document, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('update', $enquiry);

        $document = $service->findByUuidOrFail($enquiry, $document);

        $service->deletable($enquiry, $document);

        $document->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('student.document.document')]),
        ]);
    }

    public function downloadMedia(string $enquiry, string $document, string $uuid, EnquiryDocumentService $service)
    {
        $enquiry = Enquiry::findByUuidOrFail($enquiry);

        $this->authorize('view', $enquiry);

        $document = $service->findByUuidOrFail($enquiry, $document);

        return $document->downloadMedia($uuid);
    }
}
