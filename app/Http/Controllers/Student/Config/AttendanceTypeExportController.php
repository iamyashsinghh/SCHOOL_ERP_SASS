<?php

namespace App\Http\Controllers\Student\Config;

use App\Http\Controllers\Controller;
use App\Services\Student\Config\AttendanceTypeListService;
use Illuminate\Http\Request;

class AttendanceTypeExportController extends Controller
{
    public function __invoke(Request $request, AttendanceTypeListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
