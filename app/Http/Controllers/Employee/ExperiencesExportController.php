<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\ExperiencesListService;
use Illuminate\Http\Request;

class ExperiencesExportController extends Controller
{
    public function __invoke(Request $request, ExperiencesListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
