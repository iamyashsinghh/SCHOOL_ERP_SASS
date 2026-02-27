@props([
    'items' => [], // ['image' => 'url', 'name' => 'Name', 'position' => 'Title', 'company' => 'Company', 'rating' => 5, 'comment' => 'Quote']
    'autoplay' => true,
    'interval' => 6000,
    'loop' => true,
    'showRating' => false,
    'showNavigation' => true,
    'showDots' => true,
    'class' => '',
])

@if (!empty($items))
    <div x-data="testimonialCarousel({{ json_encode([
        'items' => $items,
        'autoplay' => $autoplay,
        'interval' => $interval,
        'loop' => $loop,
    ]) }})" x-init="init()" @keydown.left.prevent="previous()" @keydown.right.prevent="next()"
        @mouseenter="pauseAutoplay()" @mouseleave="resumeAutoplay()" tabindex="0" role="region"
        aria-label="Customer Testimonials"
        class="{{ $class }} relative mx-auto w-full max-w-6xl select-none focus:outline-none">
        <!-- Main Carousel Container -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-50 to-white shadow-xl">
            <!-- Slides Container -->
            <div class="relative h-auto min-h-[400px]">
                <template x-for="(item, index) in items" :key="index">
                    <div x-show="currentIndex === index" x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0 transform translate-x-full"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-300"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-full" class="absolute inset-0 w-full"
                        role="tabpanel" :aria-label="`Testimonial ${index + 1} of ${items.length}`">
                        <div class="flex h-full flex-col items-center justify-center p-8 text-center md:p-12">
                            <!-- Quote Icon -->
                            <div class="mb-6">
                                <svg class="text-site-primary h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-10zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h4v10h-10z" />
                                </svg>
                            </div>

                            <!-- Testimonial Text -->
                            <blockquote
                                class="mb-8 max-w-4xl text-xl font-medium leading-relaxed text-gray-800 md:text-2xl">
                                <span x-text="item.comment"></span>
                            </blockquote>

                            <!-- Rating (if enabled) -->
                            <div x-show="{{ $showRating ? 'true' : 'false' }}" class="mb-6 flex items-center">
                                <template x-for="star in 5" :key="star">
                                    <svg class="h-5 w-5"
                                        :class="star <= (item.rating || 5) ? 'text-yellow-400' : 'text-gray-300'"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </template>
                            </div>

                            <!-- Author Info -->
                            <div class="flex flex-col items-center gap-4 md:flex-row">
                                <!-- Avatar -->
                                {{-- <div class="relative">
                                    <img :src="item.image ||
                                        'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22><rect width=%2240%22 height=%22440%22 fill=%22%23e5e7eb%22/></svg>'"
                                        :alt="item.name || 'Customer'"
                                        class="h-16 w-16 rounded-full object-cover shadow-lg ring-4 ring-white"
                                        loading="lazy">
                                </div> --}}

                                <!-- Name and Position -->
                                <div class="text-center md:text-left">
                                    <div class="text-lg font-semibold text-gray-900" x-text="item.name || 'Anonymous'">
                                    </div>
                                    <div class="text-gray-600">
                                        <span x-text="item.detail"></span>
                                        {{-- <template x-if="item.company">
                                            <span>
                                                <span x-show="item.position"> at </span>
                                                <span class="text-site-primary font-medium" x-text="item.company"></span>
                                            </span>
                                        </template> --}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Navigation Arrows -->
            <template x-if="{{ $showNavigation ? 'true' : 'false' }}">
                <div>
                    <!-- Previous Button -->
                    <button @click="previous()" :disabled="!loop && currentIndex === 0"
                        class="focus:ring-site-primary group absolute left-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-lg transition-all duration-200 hover:bg-white focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label="Previous testimonial">
                        <svg class="h-6 w-6 text-gray-700 transition-colors group-hover:text-gray-900" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Next Button -->
                    <button @click="next()" :disabled="!loop && currentIndex === items.length - 1"
                        class="focus:ring-site-primary group absolute right-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-lg transition-all duration-200 hover:bg-white focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label="Next testimonial">
                        <svg class="h-6 w-6 text-gray-700 transition-colors group-hover:text-gray-900" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <!-- Dots Indicator -->
        <template x-if="{{ $showDots ? 'true' : 'false' }}">
            <div class="mt-8 flex justify-center gap-2">
                <template x-for="(item, index) in items" :key="index">
                    <button @click="goToSlide(index)" class="h-3 w-3 rounded-full transition-all duration-200"
                        :class="currentIndex === index ? 'bg-site-primary scale-110' : 'bg-gray-300 hover:bg-gray-400'"
                        :aria-label="`Go to testimonial ${index + 1}`"
                        :aria-current="currentIndex === index ? 'true' : 'false'"></button>
                </template>
            </div>
        </template>

        <!-- Progress Bar (Optional) -->
        {{-- <div x-show="autoplay && items.length > 1" class="mt-4 h-1 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-site-primary transition-all ease-linear"
                :style="`width: ${progress}%; transition-duration: ${isPlaying ? '100ms' : '0ms'};`"></div>
        </div> --}}
    </div>

    <script>
        function testimonialCarousel(config) {
            return {
                items: config.items,
                currentIndex: 0,
                autoplay: config.autoplay,
                interval: config.interval,
                loop: config.loop,
                timer: null,
                progress: 0,
                progressTimer: null,
                isPlaying: false,
                touchStartX: null,
                touchStartY: null,

                init() {
                    if (this.autoplay && this.items.length > 1) {
                        this.startAutoplay();
                    }
                },

                startAutoplay() {
                    if (!this.autoplay || this.items.length <= 1) return;

                    this.isPlaying = true;
                    this.progress = 0;

                    // Progress animation
                    this.progressTimer = setInterval(() => {
                        this.progress += (100 / (this.interval / 100));
                        if (this.progress >= 100) {
                            this.progress = 0;
                        }
                    }, 100);

                    // Slide change timer
                    this.timer = setInterval(() => {
                        this.next();
                    }, this.interval);
                },

                stopAutoplay() {
                    this.isPlaying = false;
                    if (this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                    if (this.progressTimer) {
                        clearInterval(this.progressTimer);
                        this.progressTimer = null;
                    }
                },

                pauseAutoplay() {
                    this.stopAutoplay();
                },

                resumeAutoplay() {
                    if (this.autoplay && this.items.length > 1) {
                        this.startAutoplay();
                    }
                },

                goToSlide(index) {
                    if (index < 0) {
                        this.currentIndex = this.loop ? this.items.length - 1 : 0;
                    } else if (index >= this.items.length) {
                        this.currentIndex = this.loop ? 0 : this.items.length - 1;
                    } else {
                        this.currentIndex = index;
                    }

                    if (this.autoplay) {
                        this.stopAutoplay();
                        this.startAutoplay();
                    }
                },

                previous() {
                    this.goToSlide(this.currentIndex - 1);
                },

                next() {
                    this.goToSlide(this.currentIndex + 1);
                }
            }
        }
    </script>
@endif
