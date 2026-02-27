<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Services\Exam\CompetencyListService;
use Illuminate\Http\Request;

class CompetencyExportController extends Controller
{
    public function __invoke(Request $request, CompetencyListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
