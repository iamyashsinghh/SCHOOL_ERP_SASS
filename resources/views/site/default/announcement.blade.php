<x-site.default.layout :metaTitle="$announcement->title" metaDescription="" metaKeywords="" :publishedAt="$announcement->created_at->toIso8601String()" :modifiedAt="$announcement->updated_at->toIso8601String()"
    :imageSrc="$announcement->og_image ?? $announcement->cover_image">

    <div class="relative">
        <div class="bg-site-primary w-full bg-opacity-70">
            <x-ui.container class="py-2">
                <div class="truncate text-3xl font-semibold text-gray-200">
                    {{ $announcement->title }}
                </div>
            </x-ui.container>
        </div>
    </div>

    <section>

        <x-ui.breadcrumb :navs="[['name' => $announcement->title]]"></x-ui.breadcrumb>

        <x-ui.container class="mt-6">
            <div class="grid grid-cols-1 gap-6">
                <div class="col-span-1 space-y-6 text-gray-800 dark:text-gray-400">
                    <div class="flex justify-between">
                        <div>
                            #{{ $announcement->code_number }}
                        </div>
                        @if ($announcement->type_id)
                            <div>
                                <x-ui.badge color="custom"
                                    :color-value="$announcement->type->color">{{ $announcement->type->name }}</x-ui.badge>
                            </div>
                        @endif
                    </div>

                    @if ($announcement->excerpt)
                        <x-ui.heading heading="h6" class="text-justify text-lg">
                            {{ $announcement->excerpt }}
                        </x-ui.heading>
                    @endif

                    <div class="flex justify-between">
                        <div>
                            <i class="fas fa-calendar"></i> {{ \Cal::dateTime($announcement->created_at)->formatted }}
                        </div>
                    </div>

                    <div class="my-6">
                        {!! $announcement->description !!}
                    </div>
                </div>
            </div>
        </x-ui.container>
    </section>
</x-site.default.layout>
