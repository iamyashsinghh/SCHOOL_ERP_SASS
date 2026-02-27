@props([
    'title' => 'Total Students',
    'value' => 12345,
    'duration' => 1200,
    'decimals' => 0,
    'prefix' => '',
    'suffix' => '',
    'startOnVisible' => true,
])

<div x-data="countUp({
    end: {{ (float) $value }},
    duration: {{ (int) $duration }},
    decimals: {{ (int) $decimals }},
    prefix: @js($prefix),
    suffix: @js($suffix),
    startOnVisible: {{ $startOnVisible ? 'true' : 'false' }},
})" x-init="init()" x-intersect.once="startOnVisible && start()"
    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="text-sm font-medium text-gray-600">{{ $title }}</div>

    <div class="mt-2 flex items-baseline gap-2">
        <div class="text-4xl font-semibold tabular-nums text-gray-900">
            <span x-text="display"></span>
        </div>
        <div class="text-xs text-gray-500" x-show="suffix && !suffix.match(/%$/)"></div>
    </div>
</div>

<script>
    function countUp({
        end,
        duration = 1200,
        decimals = 0,
        prefix = '',
        suffix = '',
        startOnVisible = true
    }) {
        return {
            startOnVisible,
            end: Number(end) || 0,
            duration: Math.max(0, Number(duration) || 0),
            decimals: Math.max(0, Math.min(6, Number(decimals) || 0)),
            prefix,
            suffix,
            display: '0',
            _started: false,
            _raf: null,

            init() {
                const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (!this.startOnVisible && !prefersReduced) {
                    this.start();
                } else if (!this.startOnVisible && prefersReduced) {
                    this.display = this.format(this.end);
                }
            },

            start() {
                if (this._started) return;
                this._started = true;

                const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (prefersReduced || this.duration === 0) {
                    this.display = this.format(this.end);
                    return;
                }

                const startTime = performance.now();
                const startVal = 0;
                const endVal = this.end;
                const ease = t => 1 - Math.pow(1 - t, 3);

                const tick = (now) => {
                    const elapsed = now - startTime;
                    const t = Math.min(1, elapsed / this.duration);
                    const value = startVal + (endVal - startVal) * ease(t);
                    this.display = this.format(value);
                    if (t < 1) this._raf = requestAnimationFrame(tick);
                    else this._raf = null;
                };

                this._raf = requestAnimationFrame(tick);
            },

            format(val) {
                const fixed = Number(val).toFixed(this.decimals);
                const withCommas = fixed.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return `${this.prefix}${withCommas}${this.suffix}`;
            }
        }
    }
</script>
