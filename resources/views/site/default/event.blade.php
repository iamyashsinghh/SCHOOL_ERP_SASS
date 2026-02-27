<x-site.default.layout :metaTitle="$event->title" metaDescription="" metaKeywords="" :publishedAt="$event->created_at->toIso8601String()" :modifiedAt="$event->updated_at->toIso8601String()"
    :imageSrc="$event->og_image ?? $event->cover_image">

    <div class="relative">
        <img class="lozad h-48 w-full object-cover lg:h-96" data-src="{{ $event->cover_image }}"
            alt="{{ $event->title }}" />
        <div class="absolute right-0 top-0 mr-2 mt-2">
            <img class="h-12 w-12" src="{{ config('config.assets.icon') }}" alt="icon" />
        </div>
        <div class="bg-site-primary absolute bottom-0 w-full bg-opacity-70">
            <x-ui.container class="py-2">
                <div class="truncate text-3xl font-semibold text-gray-200">
                    {{ $event->title }}
                </div>
            </x-ui.container>
        </div>
    </div>

    <section>

        <x-ui.breadcrumb :navs="[['name' => $event->title]]"></x-ui.breadcrumb>

        <x-ui.container class="mt-6">
            <div class="grid grid-cols-1 gap-6">
                <div class="col-span-1 space-y-6 text-gray-800 dark:text-gray-400">
                    <div class="flex justify-between">
                        <div>
                            #{{ $event->code_number }}
                        </div>
                        @if ($event->type_id)
                            <div>
                                <x-ui.badge color="custom" :color-value="$event->type->color">{{ $event->type->name }}</x-ui.badge>
                            </div>
                        @endif
                    </div>

                    @if ($event->excerpt)
                        <x-ui.heading heading="h6" class="text-justify text-lg">
                            {{ $event->excerpt }}
                        </x-ui.heading>
                    @endif

                    <div class="flex justify-between">
                        <div>
                            <i class="fas fa-calendar"></i> {{ $event->duration_in_detail }} @ {{ $event->venue }}
                        </div>
                    </div>

                    <div class="my-6">
                        {!! $event->description !!}
                    </div>
                </div>
            </div>
        </x-ui.container>
    </section>
</x-site.default.layout>
