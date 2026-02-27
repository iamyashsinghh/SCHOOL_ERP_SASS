<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\QualificationsListService;
use Illuminate\Http\Request;

class QualificationsExportController extends Controller
{
    public function __invoke(Request $request, QualificationsListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
