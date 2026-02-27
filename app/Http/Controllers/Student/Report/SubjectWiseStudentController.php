<?php

namespace App\Http\Controllers\Student\Report;

use App\Http\Controllers\Controller;
use App\Services\Student\Report\SubjectWiseStudentService;
use Illuminate\Http\Request;

class SubjectWiseStudentController extends Controller
{
    public function preRequisite(Request $request, SubjectWiseStudentService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function export(Request $request, SubjectWiseStudentService $service)
    {
        return $service->export($request);
    }
}
