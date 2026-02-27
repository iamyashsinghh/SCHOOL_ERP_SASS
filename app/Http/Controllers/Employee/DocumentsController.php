<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\DocumentsRequest;
use App\Http\Resources\Employee\DocumentsResource;
use App\Models\Employee\Employee;
use App\Services\Employee\DocumentsListService;
use App\Services\Employee\DocumentsService;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:employee:read');
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
        $employee = Employee::find($request->employee_id);

        $this->authorize('selfService', $employee);

        $document = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('employee.document.document')]),
            'document' => DocumentsResource::make($document),
        ]);
    }

    public function show(Request $request, string $document, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $employee = $service->findEmployee($document);

        $document->employee = $employee;

        $document->load('type', 'media');

        return DocumentsResource::make($document);
    }

    public function update(DocumentsRequest $request, string $document, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $employee = $service->findEmployee($document);

        $this->authorize('selfService', $employee);

        $service->update($request, $document);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('employee.document.document')]),
        ]);
    }

    public function destroy(Request $request, string $document, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $employee = $service->findEmployee($document);

        $this->authorize('manageEmployeeRecord', $employee);

        $service->deletable($request, $document);

        $document->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('employee.document.document')]),
        ]);
    }

    public function downloadMedia(string $document, string $uuid, DocumentsService $service)
    {
        $document = $service->findByUuidOrFail($document);

        $employee = $service->findEmployee($document);

        return $document->downloadMedia($uuid);
    }
}
