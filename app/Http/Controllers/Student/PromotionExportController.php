<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\PromotionListService;
use Illuminate\Http\Request;

class PromotionExportController extends Controller
{
    public function __invoke(Request $request, PromotionListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
