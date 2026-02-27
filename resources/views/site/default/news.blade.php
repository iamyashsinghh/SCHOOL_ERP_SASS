<x-site.default.layout :robots="$news->getSeo('robots')" :metaTitle="$news->getSeo('meta_title') ?? $news->title" :metaDescription="$news->getSeo('meta_description')" :metaKeywords="$news->getSeo('meta_keywords')" :publishedAt="$news->published_at->toIso8601String()"
    :modifiedAt="$news->updated_at->toIso8601String()" :imageSrc="$news->og_image ?? $news->cover_image">

    <div class="relative">
        <img class="lozad h-48 w-full object-cover lg:h-96" data-src="{{ $news->cover_image }}"
            alt="{{ $news->title }}" />
        <div class="absolute right-0 top-0 mr-2 mt-2">
            <img class="h-12 w-12" src="{{ config('config.assets.icon') }}" alt="icon" />
        </div>
        <div class="absolute bottom-0 w-full bg-black bg-opacity-70">
            <x-ui.container class="py-2">
                <div class="truncate text-3xl font-semibold text-gray-200">
                    {{ $news->title }}
                </div>
            </x-ui.container>
        </div>
    </div>

    <section>

        <x-ui.breadcrumb :navs="[
            ['name' => trans('news.news'), 'url' => route('site.page', ['slug' => request()->route('slug')])],
            ['name' => $news->title],
        ]"></x-ui.breadcrumb>

        <x-ui.container class="mt-6">
            <div class="grid grid-cols-1 gap-6">
                <div class="col-span-1 space-y-6 text-gray-800 dark:text-gray-400">
                    <x-ui.heading heading="h1">
                        {{ $news->title }}
                    </x-ui.heading>

                    @if ($news->sub_title)
                        <x-ui.heading heading="h6" class="texl-xl text-justify">
                            {{ $news->sub_title }}
                        </x-ui.heading>
                    @endif

                    <div class="flex justify-between">
                        @if ($news->category)
                            <p class="text-sm font-medium">
                                <a href="{{ route('site.page.news-list-category', ['slug' => request()->route('slug'), 'category' => $news->category->slug]) }}"
                                    class="hover:underline">
                                    <x-ui.badge color="custom" :color-value="$news->category->color">{{ $news->category->name }}
                                    </x-ui.badge>
                                </a>
                            </p>
                        @endif
                        <p class="text-sm font-medium">
                            <i class="fas fa-calendar"></i> {{ $news->published_at->formatted }}
                        </p>
                    </div>

                    @if ($news->tags->count())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($news->tags as $tag)
                                <a class="rounded-lg bg-gray-200 px-2 py-1 text-sm text-gray-800"
                                    href="{{ route('site.page.news-list-tag', ['slug' => request()->route('slug'), 'tag' => strtolower($tag->name)]) }}">#{{ $tag->name }}</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="md-content">
                        {!! $news->content !!}
                    </div>
                </div>
            </div>
        </x-ui.container>
    </section>

    @if ($relatedTagsNews)
        <section class="mb-10">
            <x-ui.container class="space-y-6">

                <x-ui.heading heading="h4">
                    {{ trans('news.related_news') }}
                </x-ui.heading>
                <div class="grid max-w-lg gap-5 lg:max-w-none lg:grid-cols-1">
                    <x-site.default.news-card :news="$relatedTagsNews" :menu="$menu" />
                </div>
            </x-ui.container>
        </section>
    @endif

    @if ($relatedCategoryNews->count())
        <section class="mb-10">
            <x-ui.container class="space-y-6">
                <x-ui.heading heading="h3">
                    {{ trans('news.related_news') }}
                </x-ui.heading>
                <div class="grid max-w-lg gap-5 lg:max-w-none lg:grid-cols-3">
                    @foreach ($relatedCategoryNews as $news)
                        <x-site.default.news-card :news="$news" :menu="$menu" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.hljs.highlightAll();
        });
    </script>
</x-site.default.layout>
