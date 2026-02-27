<?php

namespace App\Http\Controllers\News;

use App\Http\Controllers\Controller;
use App\Http\Requests\News\NewsRequest;
use App\Http\Resources\News\NewsResource;
use App\Http\Resources\News\NewsSummaryResource;
use App\Models\News\News;
use App\Services\News\NewsListService;
use App\Services\News\NewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function preRequisite(NewsService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, NewsListService $service)
    {
        $this->authorize('viewAny', News::class);

        return $service->paginate($request);
    }

    public function store(NewsRequest $request, NewsService $service)
    {
        $this->authorize('create', News::class);

        $news = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('news.news')]),
            'news' => NewsSummaryResource::make($news),
        ]);
    }

    public function show(string $news, NewsService $service): NewsResource
    {
        $news = $service->findByUuidOrFail($news);

        $news->load('category', 'media', 'tags');

        $this->authorize('view', $news);

        return NewsResource::make($news);
    }

    public function update(NewsRequest $request, string $news, NewsService $service)
    {
        $news = $service->findByUuidOrFail($news);

        $this->authorize('update', $news);

        $service->update($request, $news);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('news.news')]),
        ]);
    }

    public function destroy(string $news, NewsService $service)
    {
        $news = $service->findByUuidOrFail($news);

        $this->authorize('delete', $news);

        $service->deletable($news);

        $news->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('news.news')]),
        ]);
    }

    public function downloadMedia(string $news, string $uuid, NewsService $service)
    {
        $news = $service->findByUuidOrFail($news);

        $this->authorize('view', $news);

        return $news->downloadMedia($uuid);
    }

    public function archiveMultiple(Request $request, NewsService $service)
    {
        $this->authorize('update', News::class);

        $count = $service->archiveMultiple($request);

        return response()->success([
            'message' => trans('global.multiple_archived', ['count' => $count, 'attribute' => trans('news.news')]),
        ]);
    }

    public function unarchiveMultiple(Request $request, NewsService $service)
    {
        $this->authorize('update', News::class);

        $count = $service->unarchiveMultiple($request);

        return response()->success([
            'message' => trans('global.multiple_unarchived', ['count' => $count, 'attribute' => trans('news.news')]),
        ]);
    }

    public function destroyMultiple(Request $request, NewsService $service)
    {
        $this->authorize('delete', News::class);

        $count = $service->deleteMultiple($request);

        return response()->success([
            'message' => trans('global.multiple_deleted', ['count' => $count, 'attribute' => trans('news.news')]),
        ]);
    }
}
