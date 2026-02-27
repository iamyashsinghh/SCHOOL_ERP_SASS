<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Services\Communication\PushMessageListService;
use Illuminate\Http\Request;

class PushMessageExportController extends Controller
{
    public function __invoke(Request $request, PushMessageListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
