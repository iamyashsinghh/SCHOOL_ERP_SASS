<?php

namespace App\Http\Controllers\News;

use App\Http\Controllers\Controller;
use App\Services\News\NewsListService;
use Illuminate\Http\Request;

class NewsExportController extends Controller
{
    public function __invoke(Request $request, NewsListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
