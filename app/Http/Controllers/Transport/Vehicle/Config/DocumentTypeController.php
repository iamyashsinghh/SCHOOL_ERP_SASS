<?php

namespace App\Http\Controllers\Transport\Vehicle\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\Vehicle\Config\DocumentTypeRequest;
use App\Http\Resources\Transport\Vehicle\Config\DocumentTypeResource;
use App\Models\Tenant\Option;
use App\Services\Transport\Vehicle\Config\DocumentTypeListService;
use App\Services\Transport\Vehicle\Config\DocumentTypeService;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:transport:config')->except(['index', 'show']);
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
            'message' => trans('global.created', ['attribute' => trans('transport.vehicle.document_type.document_type')]),
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
            'message' => trans('global.updated', ['attribute' => trans('transport.vehicle.document_type.document_type')]),
        ]);
    }

    public function destroy(Request $request, Option $documentType, DocumentTypeService $service)
    {
        $service->deletable($request, $documentType);

        $documentType->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('transport.vehicle.document_type.document_type')]),
        ]);
    }
}
