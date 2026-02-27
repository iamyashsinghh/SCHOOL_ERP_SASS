@if (empty($announcements))
    <x-ui.empty-state icon="users" title="No announcements yet" description="No announcements found." class="my-12" />
@endif

@foreach ($announcements as $teamName => $teamAnnouncements)
    {{-- <div>
        <h2 class="mb-4 text-2xl font-bold">{{ $teamName }}</h2>
    </div> --}}

    <div class="grid max-w-none grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($teamAnnouncements as $announcement)
            <div class="flex flex-col rounded-lg border border-gray-200 p-2 shadow-lg dark:border-gray-700">
                <div class="flex-shrink-0">
                    <a
                        href="{{ '/pages/announcements/' . Str::slug($announcement->title) . '/' . $announcement->uuid }}">
                        <div class="w-full">
                            <div class="truncate px-2 text-lg font-semibold">
                                {{ $announcement->title }}
                            </div>
                        </div>
                    </a>
                </div>
                <div class="flex flex-1 flex-col justify-between bg-white p-2 dark:bg-gray-800">
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <p class="text-site-primary text-sm font-medium dark:text-gray-300">
                                <i class="fas fa-calendar"></i>
                                {{ \Cal::dateTime($announcement->created_at)->formatted }}
                            </p>
                        </div>

                        @if ($announcement->type_id)
                            <p class="text-site-primary mt-2 text-sm font-medium">
                                <x-ui.badge color="custom"
                                    :color-value="$announcement->type->color">{{ $announcement->type->name }}</x-ui.badge>
                            </p>
                        @endif
                    </div>

                    @if ($announcement->excerpt)
                        <p class="mt-2 text-sm italic">{{ $announcement->excerpt }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach

{{-- {{ $announcements->links() }} --}}
