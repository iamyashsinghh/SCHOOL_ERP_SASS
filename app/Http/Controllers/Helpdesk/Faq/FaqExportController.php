<?php

namespace App\Http\Controllers\Helpdesk\Faq;

use App\Http\Controllers\Controller;
use App\Services\Helpdesk\Faq\FaqListService;
use Illuminate\Http\Request;

class FaqExportController extends Controller
{
    public function __invoke(Request $request, FaqListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
