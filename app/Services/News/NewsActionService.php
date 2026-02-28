<?php

namespace App\Services\News;

use App\Actions\CreateTag;
use App\Concerns\HasStorage;
use App\Enums\News\Status;
use App\Helpers\CalHelper;
use App\Models\Tenant\News\News;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NewsActionService
{
    use HasStorage;

    public function updateMeta(Request $request, News $news)
    {
        $request->validate([
            'published_at' => 'nullable|date_format:Y-m-d H:i:s',
            'slug' => ['required', 'min:3', 'max:255', 'alpha_dash', Rule::unique('news')->ignore($news->id)],
            'seo.robots' => 'boolean',
            'seo.meta_title' => 'nullable|min:3|max:255',
            'seo.meta_description' => 'nullable|min:3|max:255',
            'seo.meta_keywords' => 'nullable|min:3|max:255',
            'category' => 'nullable|uuid',
            'tags' => 'array',
            'tags.*' => 'required|string|distinct',
        ], [], [
            'published_at' => trans('news.props.published_at'),
            'slug' => trans('news.props.slug_placeholder'),
            'seo.robots' => trans('news.props.seo.robots'),
            'seo.meta_title' => trans('news.props.seo.meta_title'),
            'seo.meta_description' => trans('news.props.seo.meta_description'),
            'seo.meta_keywords' => trans('news.props.seo.meta_keywords'),
            'category' => trans('news.category.category'),
            'tags' => trans('general.tag'),
        ]);

        $newsCategory = $request->category ? Option::whereType('news_category')->whereUuid($request->category)->getOrFail(trans('news.category.category'), 'category') : null;

        $news->status = $request->published_at ? Status::PUBLISHED : Status::DRAFT;
        $news->published_at = CalHelper::storeDateTime($request->published_at)?->toDateTimeString();
        $news->category_id = $newsCategory?->id;
        $news->slug = $request->slug;
        $news->seo = [
            'robots' => $request->boolean('seo.robots'),
            'meta_title' => $request->input('seo.meta_title'),
            'meta_description' => $request->input('seo.meta_description'),
            'meta_keywords' => $request->input('seo.meta_keywords'),
        ];
        $news->save();

        $tags = (new CreateTag)->execute($request->input('tags', []));

        $news->tags()->sync($tags);

        $news->updateMedia($request);
    }

    public function uploadAsset(Request $request, News $news, string $type)
    {
        request()->validate([
            'image' => 'required|image',
        ]);

        $assets = $news->assets;
        $asset = Arr::get($assets, $type);

        $this->deleteImageFile(
            visibility: 'public',
            path: $asset,
        );

        $image = $this->uploadImageFile(
            visibility: 'public',
            path: 'news/assets/'.$type,
            input: 'image',
            url: false
        );

        $assets[$type] = $image;
        $news->assets = $assets;
        $news->save();
    }

    public function removeAsset(Request $request, News $news, string $type)
    {
        $assets = $news->assets;
        $asset = Arr::get($assets, $type);

        $this->deleteImageFile(
            visibility: 'public',
            path: $asset,
        );

        unset($assets[$type]);
        $news->assets = $assets;
        $news->save();
    }

    public function archive(Request $request, News $news): void
    {
        if ($news->archived_at->value) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $news->archived_at = now();
        $news->save();
    }

    public function unarchive(Request $request, News $news): void
    {
        if (empty($news->archived_at->value)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $news->archived_at = null;
        $news->save();
    }

    public function pin(Request $request, News $news): void
    {
        if ($news->pinned_at->value) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $news->pinned_at = now();
        $news->save();
    }

    public function unpin(Request $request, News $news): void
    {
        if (empty($news->pinned_at->value)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $news->pinned_at = null;
        $news->save();
    }
}
