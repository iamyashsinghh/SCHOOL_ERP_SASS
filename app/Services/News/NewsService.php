<?php

namespace App\Services\News;

use App\Enums\News\Status;
use App\Models\Tenant\News\News;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsService
{
    public function preRequisite(): array
    {
        $url = config('app.url').'/news/';

        return compact('url');
    }

    public function findByUuidOrFail(string $uuid): News
    {
        return News::query()
            ->findIfExists($uuid);
    }

    public function create(Request $request): News
    {
        \DB::beginTransaction();

        $news = News::forceCreate($this->formatParams($request));

        \DB::commit();

        return $news;
    }

    private function formatParams(Request $request, ?News $news = null): array
    {
        $formatted = [
            'title' => $request->title,
            'sub_title' => $request->sub_title,
            'content' => $request->content,
        ];

        if (! $news) {
            $formatted['slug'] = Str::slug($request->title).'-'.uniqid();
            $formatted['status'] = Status::DRAFT;
            $formatted['seo']['robots'] = true;
        }

        return $formatted;
    }

    public function update(Request $request, News $news): void
    {
        \DB::beginTransaction();

        $news->forceFill($this->formatParams($request, $news))->save();

        \DB::commit();
    }

    public function deletable(News $news, $validate = false): ?bool
    {
        return true;
    }

    private function findMultiple(Request $request): array
    {
        if ($request->boolean('global')) {
            $listService = new NewsListService;
            $uuids = $listService->getIds($request);
        } else {
            $uuids = is_array($request->uuids) ? $request->uuids : [];
        }

        if (! count($uuids)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('general.data')])]);
        }

        return $uuids;
    }

    public function archiveMultiple(Request $request): int
    {
        $uuids = $this->findMultiple($request);

        $news = News::whereIn('uuid', $uuids)->get();

        $archivable = [];
        foreach ($news as $news) {
            if (empty($news->archived_at->value)) {
                $archivable[] = $news->uuid;
            }
        }

        if (! count($archivable)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_update_any', ['attribute' => trans('news.news')])]);
        }

        News::whereIn('uuid', $archivable)->update(['archived_at' => now()]);

        return count($archivable);
    }

    public function unarchiveMultiple(Request $request): int
    {
        $request->merge(['is_archived' => true]);
        $uuids = $this->findMultiple($request);

        $news = News::whereIn('uuid', $uuids)->get();

        $unarchivable = [];
        foreach ($news as $news) {
            if ($news->archived_at->value) {
                $unarchivable[] = $news->uuid;
            }
        }

        if (! count($unarchivable)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_update_any', ['attribute' => trans('news.news')])]);
        }

        News::whereIn('uuid', $unarchivable)->update(['archived_at' => null]);

        return count($unarchivable);
    }

    public function deleteMultiple(Request $request): int
    {
        $uuids = $this->findMultiple($request);

        $news = News::whereIn('uuid', $uuids)->get();

        $deletable = [];
        foreach ($news as $news) {
            if ($this->deletable($news, true)) {
                $deletable[] = $news->uuid;
            }
        }

        if (! count($deletable)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_delete_any', ['attribute' => trans('news.news')])]);
        }

        News::whereIn('uuid', $deletable)->delete();

        return count($deletable);
    }
}
