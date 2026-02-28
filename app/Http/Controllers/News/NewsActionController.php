<?php

namespace App\Http\Controllers\News;

use App\Http\Controllers\Controller;
use App\Models\Tenant\News\News;
use App\Services\News\NewsActionService;
use Illuminate\Http\Request;

class NewsActionController extends Controller
{
    public function updateMeta(Request $request, NewsActionService $service, News $news)
    {
        $this->authorize('update', $news);

        $service->updateMeta($request, $news);

        return response()->success(['message' => trans('global.updated', ['attribute' => trans('news.news')])]);
    }

    public function uploadAsset(Request $request, NewsActionService $service, News $news, string $type)
    {
        $this->authorize('update', $news);

        $service->uploadAsset($request, $news, $type);

        return response()->ok();
    }

    public function removeAsset(Request $request, NewsActionService $service, News $news, string $type)
    {
        $this->authorize('update', $news);

        $service->removeAsset($request, $news, $type);

        return response()->ok();
    }

    public function archive(Request $request, News $news, NewsActionService $service)
    {
        $this->authorize('update', $news);

        $service->archive($request, $news);

        return response()->success([
            'message' => trans('global.archived', ['attribute' => trans('news.news')]),
        ]);
    }

    public function unarchive(Request $request, News $news, NewsActionService $service)
    {
        $this->authorize('update', $news);

        $service->unarchive($request, $news);

        return response()->success([
            'message' => trans('global.unarchived', ['attribute' => trans('news.news')]),
        ]);
    }

    public function pin(Request $request, News $news, NewsActionService $service)
    {
        $this->authorize('update', $news);

        $service->pin($request, $news);

        return response()->success([
            'message' => trans('global.pinned', ['attribute' => trans('news.news')]),
        ]);
    }

    public function unpin(Request $request, News $news, NewsActionService $service)
    {
        $this->authorize('update', $news);

        $service->unpin($request, $news);

        return response()->success([
            'message' => trans('global.unpinned', ['attribute' => trans('news.news')]),
        ]);
    }
}
