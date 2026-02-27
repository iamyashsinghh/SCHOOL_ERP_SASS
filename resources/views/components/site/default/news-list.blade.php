@props(['menu'])

@if ($news->count() == 0)
    <x-ui.empty-state icon="users" title="No news yet" description="No news available at the moment." class="my-12" />
@endif

@if ($news)
    <div class="grid max-w-none grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($news as $newsItem)
            <x-site.default.news-card :news="$newsItem" :menu="$menu" />
        @endforeach
    </div>
@endif

@if ($type == 'list')
    {{ $news->links() }}
@endif
