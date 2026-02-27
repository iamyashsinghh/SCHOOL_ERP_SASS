@props(['menu'])

@if ($blogs->count() == 0)
    <x-ui.empty-state icon="users" title="No blogs yet" description="No blogs found." class="my-12" />
@endif

@if ($blogs)
    <div class="grid max-w-none grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($blogs as $blog)
            <x-site.default.blog-card :blog="$blog" :menu="$menu" />
        @endforeach
    </div>
@endif

@if ($type == 'list')
    {{ $blogs->links() }}
@endif
