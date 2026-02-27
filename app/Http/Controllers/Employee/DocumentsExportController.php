<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\DocumentsListService;
use Illuminate\Http\Request;

class DocumentsExportController extends Controller
{
    public function __invoke(Request $request, DocumentsListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
