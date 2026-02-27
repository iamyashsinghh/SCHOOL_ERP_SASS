@props([
    'title' => 'Welcome',
    'description' => '',
])

<div x-data="{
    open: false,
    cookieName: 'popup_modal_closed',
    days: 1,
    delay: 2000,

    getCookie(name) {
        // No regex – robust and simple
        const parts = ('; ' + document.cookie).split('; ' + encodeURIComponent(name) + '=');
        if (parts.length === 2) {
            return decodeURIComponent(parts.pop().split(';').shift());
        }
        return null;
    },

    setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie =
            encodeURIComponent(name) + '=' + encodeURIComponent(value) +
            '; expires=' + d.toUTCString() +
            '; path=/; SameSite=Lax';
    },

    showIfNeeded() {
        if (!this.getCookie(this.cookieName)) {
            setTimeout(() => { this.open = true }, this.delay);
        }
    },

    close() {
        this.setCookie(this.cookieName, '1', this.days);
        this.open = false;
    }
}" x-init="showIfNeeded()" @keydown.escape.window="open && close()" class="relative">
    <!-- Backdrop -->
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[60] bg-black/50" @click="close()" aria-hidden="true">
    </div>

    <!-- Dialog -->
    <div x-show="open" x-transition x-trap.noscroll="open"
        class="fixed inset-0 z-[61] flex items-start justify-center overscroll-contain p-4 md:items-center"
        role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title">
        <div class="flex max-h-[90dvh] w-full max-w-lg flex-col rounded-xl bg-white shadow-2xl ring-1 ring-black/5">
            <!-- Header -->
            <div class="flex flex-none items-start justify-between gap-4 border-b px-5 py-4">
                <h2 id="cookie-modal-title" class="text-lg font-semibold">{{ $title }}</h2>
                <button type="button" @click="close()"
                    class="focus:ring-site-primary rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2"
                    aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 8.586 4.707 3.293a1 1 0 0 0-1.414 1.414L8.586 10l-5.293 5.293a1 1 0 1 0 1.414 1.414L10 11.414l5.293 5.293a1 1 0 0 0 1.414-1.414L11.414 10l5.293-5.293A1 1 0 0 0 15.293 3.293L10 8.586z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <!-- Body (scrolls) -->
            <div
                class="flex-1 overflow-y-auto px-5 py-4 [&_*]:max-w-full [&_*]:break-words [&_img]:h-auto [&_img]:max-w-full">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <div class="flex flex-none items-center justify-end gap-2 px-5 pb-5">
                <button type="button" @click="close()"
                    class="bg-site-primary hover:bg-site-dark-primary focus:ring-site-primary inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-white shadow focus:outline-none focus:ring-2">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
