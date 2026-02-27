<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\DocumentsListService;
use Illuminate\Http\Request;

class DocumentsExportController extends Controller
{
    public function __invoke(Request $request, DocumentsListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
