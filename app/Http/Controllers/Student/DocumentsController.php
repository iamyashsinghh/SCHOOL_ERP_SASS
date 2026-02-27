<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\DocumentsRequest;
use App\Http\Resources\Student\DocumentsResource;
use App\Services\Student\DocumentsListService;
use App\Services\Student\DocumentsService;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:student:read');
    }

    public function preRequisite(Request $request, DocumentsService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, DocumentsListService $service)
    {
        return $service->paginate($request);
    }

    public function store(DocumentsRequest $request, DocumentsService $service)
    {
        $document = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('student.document.document')]),
            'document' => DocumentsResource::make($document),
        ]);
    }

    public function show(Request $request, string $document, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $student = $service->findStudent($document);

        $document->student = $student;

        $document->load('type', 'media');

        return DocumentsResource::make($document);
    }

    public function update(DocumentsRequest $request, string $document, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $student = $service->findStudent($document);

        $service->update($request, $document);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.document.document')]),
        ]);
    }

    public function destroy(Request $request, string $document, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $student = $service->findStudent($document);

        $service->deletable($request, $document);

        $document->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('student.document.document')]),
        ]);
    }

    public function downloadMedia(string $document, string $uuid, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $student = $service->findStudent($document);

        return $document->downloadMedia($uuid);
    }
}
