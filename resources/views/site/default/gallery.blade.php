<x-site.default.layout :metaTitle="$gallery->title" :metaDescription="$gallery->excerpt" metaKeywords="" :publishedAt="$gallery->created_at->toIso8601String()" :modifiedAt="$gallery->updated_at->toIso8601String()"
    :imageSrc="$gallery->thumbnail_url">

    <div class="relative">
        <div class="bg-site-primary w-full bg-opacity-70">
            <x-ui.container class="py-2">
                <div class="truncate text-3xl font-semibold text-gray-200">
                    {{ $gallery->title }}
                </div>
            </x-ui.container>
        </div>
    </div>

    <section>

        <x-ui.breadcrumb :navs="[['name' => $gallery->title]]"></x-ui.breadcrumb>

        <x-ui.container class="mt-6">
            <div class="grid grid-cols-1 gap-6">
                <div class="col-span-1 space-y-6 text-gray-800 dark:text-gray-400">
                    @if ($gallery->excerpt)
                        <x-ui.heading heading="h6" class="text-justify text-lg">
                            {{ $gallery->excerpt }}
                        </x-ui.heading>
                    @endif

                    <div class="flex justify-between">
                        <div>
                            <i class="fas fa-calendar"></i> {{ $gallery->date->formatted }}
                        </div>
                    </div>

                    <div class="my-6">
                        {!! $gallery->description !!}
                    </div>
                </div>
            </div>

            <div class="my-12">

                <x-ui.image-gallery :images="$gallery->images
                    ->map(
                        fn($image) => [
                            'url' => $image->url,
                            'thumbnail' => $image->thumbnail_url,
                            'alt' => $gallery->title,
                            'caption' => $gallery->title,
                        ],
                    )
                    ->toArray()" />

            </div>
        </x-ui.container>
    </section>
</x-site.default.layout>
