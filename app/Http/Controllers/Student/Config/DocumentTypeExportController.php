<?php

namespace App\Http\Controllers\Student\Config;

use App\Http\Controllers\Controller;
use App\Services\Student\Config\DocumentTypeListService;
use Illuminate\Http\Request;

class DocumentTypeExportController extends Controller
{
    public function __invoke(Request $request, DocumentTypeListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
