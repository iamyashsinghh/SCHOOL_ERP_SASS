<?php

namespace App\Http\Controllers\Employee\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\Config\DocumentTypeRequest;
use App\Http\Resources\Employee\Config\DocumentTypeResource;
use App\Models\Option;
use App\Services\Employee\Config\DocumentTypeListService;
use App\Services\Employee\Config\DocumentTypeService;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:employee:config')->except(['index', 'show']);
    }

    public function preRequisite(Request $request, DocumentTypeService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, DocumentTypeListService $service)
    {
        return $service->paginate($request);
    }

    public function store(DocumentTypeRequest $request, DocumentTypeService $service)
    {
        $documentType = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('employee.document_type.document_type')]),
            'document_type' => DocumentTypeResource::make($documentType),
        ]);
    }

    public function show(Option $documentType, DocumentTypeService $service)
    {
        return DocumentTypeResource::make($documentType);
    }

    public function update(DocumentTypeRequest $request, Option $documentType, DocumentTypeService $service)
    {
        $service->update($request, $documentType);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('employee.document_type.document_type')]),
        ]);
    }

    public function destroy(Request $request, Option $documentType, DocumentTypeService $service)
    {
        $service->deletable($request, $documentType);

        $documentType->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('employee.document_type.document_type')]),
        ]);
    }
}
