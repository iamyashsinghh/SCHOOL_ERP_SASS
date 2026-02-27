@if (!empty($items))
    <header class="w-full overflow-hidden border-b border-gray-800 bg-black py-2 text-white">
        <div class="relative flex w-full overflow-hidden">
            <div x-data="{ isPaused: false }" @mouseenter="isPaused = true" @mouseleave="isPaused = false"
                :class="{ 'marquee-paused': isPaused }"
                class="animate-marquee flex space-x-2 transition-all duration-300 ease-in-out"
                style="--marquee-speed: {{ $speed ?? '30s' }};">
                @foreach ($items as $item)
                    <a href="{{ Arr::get($item, 'url') }}"
                        class="whitespace-nowrap px-2">{{ Arr::get($item, 'title') }}</a>
                @endforeach
                @foreach ($items as $item)
                    <a href="{{ Arr::get($item, 'url') }}"
                        class="whitespace-nowrap px-2">{{ Arr::get($item, 'title') }}</a>
                @endforeach
            </div>
        </div>
    </header>
@endif

<div class="bg-site-primary flex h-12 items-center justify-end px-10 text-gray-200">
    <div class="sm:container">
        <div class="flex justify-end sm:justify-between">

            <div class="hidden sm:block">
                @if (config('config.general.app_email'))
                    Email: {{ config('config.general.app_email') }}
                @endif
                @if (config('config.general.app_phone'))
                    | Phone: {{ config('config.general.app_phone') }}
                @endif
            </div>

            <div class="">
                <a href="/app/payment">Online Fee Payment</a> | <a href="/app/online-registration">Online
                    Registration</a>
            </div>
        </div>
    </div>
</div>

@if ($announcementPopup)
    <x-site.popup-modal :title="$announcementPopup->title">
        {!! $announcementPopup->description !!}
        {!! $announcementPopup->description !!}
        {!! $announcementPopup->description !!}
    </x-site.popup-modal>
@endif
