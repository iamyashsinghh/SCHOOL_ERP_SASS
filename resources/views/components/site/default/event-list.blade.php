@if (empty($events))
    <x-ui.empty-state icon="users" title="No events yet" description="No events found." class="my-12" />
@endif

@foreach ($events as $teamName => $teamEvents)
    {{-- <div>
        <h2 class="mb-4 text-2xl font-bold">{{ $teamName }}</h2>
    </div> --}}

    <div class="grid max-w-none grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($teamEvents as $event)
            <div class="flex flex-col overflow-hidden rounded-lg border border-gray-200 shadow-lg dark:border-gray-700">
                <div class="flex-shrink-0">
                    <div class="relative">
                        <a href="{{ '/pages/events/' . Str::slug($event->title) . '/' . $event->uuid }}">
                            <img class="lozad h-48 w-full object-cover" data-src="{{ $event->cover_image }}"
                                alt="{{ $event->title }}" />
                        </a>
                        <div class="absolute right-0 top-0 mr-2 mt-2">
                            <img class="h-8 w-8" src="{{ config('config.assets.icon') }}" alt="icon" />
                        </div>
                        <div class="absolute bottom-0 w-full bg-black bg-opacity-70">
                            <div class="truncate px-4 text-lg font-semibold text-gray-200">
                                {{ $event->title }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-1 flex-col justify-between bg-white p-6 dark:bg-gray-800">
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <p class="text-site-primary text-sm font-medium dark:text-gray-300">
                                <i class="fas fa-calendar"></i> {{ $event->duration_in_detail }}
                                @if ($event->venue)
                                    <span class="text-gray-500 dark:text-gray-400"> at {{ $event->venue }}</span>
                                @endif
                            </p>
                        </div>

                        @if ($event->type_id)
                            <p class="text-site-primary mt-2 text-sm font-medium">
                                <x-ui.badge color="custom" :color-value="$event->type->color">{{ $event->type->name }}</x-ui.badge>
                            </p>
                        @endif

                        @if ($event->excerpt)
                            <p class="mt-2 text-sm italic">{{ $event->excerpt }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach

{{-- {{ $events->links() }} --}}
