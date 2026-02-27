<div class="{{ $block->has_cover && $block->cover_image ? 'border-gray-100 border-2 rounded-md shadow-md' : '' }} @if ($fullHeight) h-full @endif flex w-full flex-col text-gray-700"
    style="@if ($block->background_color) background-color: {{ $block->background_color }}; @endif @if ($block->text_color) color: {{ $block->text_color }}; @endif">
    @if ($block->has_cover && $block->cover_image)
        <img src="{{ $block->cover_image }}" alt="{{ $block->title }}" class="block h-auto w-full rounded-t-md">
    @endif
    <div class="{{ $block->has_cover && $block->cover_image ? '' : '' }} flex flex-1 flex-col px-4 py-2">
        <h2 class="@if ($itemCount == 1) !text-center @endif text-xl font-bold"
            style="@if ($block->text_color) color: {{ $block->text_color }}; @endif">
            {{ $block->title }}</h2>
        @if ($block->sub_title)
            <p class="@if ($itemCount == 1) !text-center @endif text-sm">{{ $block->sub_title }}</p>
        @endif
        @if ($block->type?->value == 'accordion')
            <div class="mt-2">
                <x-site.accordion :items="$block->getMeta('accordion_items', [])" />
            </div>
        @elseif ($block->type?->value == 'stat_counter')
            <div class="mt-8">
                <div class="grid-cols-{{ $block->getMeta('max_items_per_row', 2) }} grid gap-4">
                    @foreach ($block->getMeta('stat_counter_items', []) as $item)
                        <div class="col-span-{{ $block->getMeta('max_items_per_row', 2) }} sm:col-span-1">
                            <x-site.stat-box :title="$item['heading']" :value="(int) $item['count']" duration="2500" />
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($block->type?->value == 'testimonial')
            <div class="my-8">
                <x-site.testimonial :items="$block->getMeta('testimonial_items', [])" :autoplay="true" interval="6000" :loop="true" />
            </div>
        @else
            <p class="mt-2 flex-1">{{ Str::limit($block->content, 1000) }}</p>
        @endif
        @if ($block->url)
            <div class="mt-2">
                @if ($block->background_color)
                    <a href="{{ $block->url }}" target="{{ $block->target_url }}" class=""
                        style="color: {{ $block->text_color }}">Read
                        More</a>
                @else
                    <a href="{{ $block->url }}" target="{{ $block->target_url }}"
                        class="bg-site-primary button mt-auto rounded-md px-4 py-2 text-xs">Read
                        More</a>
                @endif
            </div>
        @endif
    </div>
</div>
