<!-- Add `group` here so md:group-hover works -->
<div x-data="{ flipped: false }" class="group relative h-full w-full [perspective:1200px]">

    <!-- 1) Invisible sizer sets natural height (doesn't block pointer events) -->
    <div class="pointer-events-none invisible">
        <x-site.block-content :block="$block" />
    </div>

    <!-- 2) Rotor fills container; flips on hover (md+) and on click -->
    <div class="absolute inset-0 transition-transform duration-500 will-change-transform [transform-style:preserve-3d] [transform:rotateY(0deg)] md:group-hover:[transform:rotateY(180deg)]"
        :class="flipped ? '[transform:rotateY(180deg)]' : ''" role="button" tabindex="0"
        @click="if (!$event.target.closest('a,button')) flipped = !flipped" @keydown.enter.prevent="flipped = !flipped"
        @keydown.space.prevent="flipped = !flipped">
        <!-- FRONT -->
        <div class="absolute inset-0 overflow-hidden [backface-visibility:hidden]">
            <x-site.block-content :block="$block" :full-height="true" />
        </div>

        <!-- BACK (same content) -->
        <div class="absolute inset-0 overflow-hidden [backface-visibility:hidden] [transform:rotateY(180deg)]">
            <x-site.block-content :block="$block" :full-height="true" />
        </div>

    </div>
</div>
