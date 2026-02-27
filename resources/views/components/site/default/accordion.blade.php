@props([
    'items' => [],
    'multiple' => false,
    'defaultOpen' => [],
])

<div x-data="(() => {
    const multi = {{ $multiple ? 'true' : 'false' }};
    const openInit = @js($defaultOpen);
    if (multi) {
        const state = {};
        (Array.isArray(openInit) ? openInit : []).forEach(i => state[i] = true);
        return { multiple: true, open: state, toggle(i) { this.open[i] = !this.open[i] } };
    } else {
        const idx = Number.isInteger(openInit) ? openInit : null;
        return { multiple: false, openIndex: idx, toggle(i) { this.openIndex = this.openIndex === i ? null : i } };
    }
})()" class="w-full divide-y divide-gray-200 rounded-md border border-gray-200 bg-white">
    @foreach ($items as $i => $panel)
        @php
            $heading = $panel['heading'] ?? '';
            $desc = $panel['description'] ?? '';
        @endphp
        <section class="group">
            <h3>
                <button type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    @click="toggle({{ $i }})"
                    :aria-expanded="{{ $multiple ? "!!open[$i]" : "openIndex === $i" }}"
                    :aria-controls="'accordion-panel-{{ $i }}'" id="accordion-header-{{ $i }}">
                    <span class="text-sm font-medium text-gray-900">{{ $heading }}</span>
                    <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform duration-200"
                        :class="{{ $multiple ? "open[$i] ? 'rotate-180' : ''" : "openIndex === $i ? 'rotate-180' : ''" }}"
                        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.17l3.71-2.94a.75.75 0 111.04 1.08l-4.24 3.36a.75.75 0 01-.94 0L5.21 8.31a.75.75 0 01.02-1.1z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </h3>

            <div id="accordion-panel-{{ $i }}" role="region"
                :aria-labelledby="'accordion-header-{{ $i }}'"
                x-show="{{ $multiple ? "!!open[$i]" : "openIndex === $i" }}" x-collapse
                class="px-4 pb-4 text-sm text-gray-700">
                {!! nl2br(e($desc)) !!}
            </div>
        </section>
    @endforeach
</div>
