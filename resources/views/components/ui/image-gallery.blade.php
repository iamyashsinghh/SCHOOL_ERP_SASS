@props([
    'images' => [], // Array of images: ['url' => 'path', 'thumbnail' => 'thumb_path', 'alt' => 'description', 'caption' => 'optional caption']
    'columns' => 4, // Number of columns in grid (1-6)
    'gap' => 4, // Gap between images (1-8)
    'aspectRatio' => 'square', // 'square', 'portrait', 'landscape', 'auto'
    'showCaptions' => false, // Show captions on thumbnails
    'enableZoom' => true, // Enable zoom in lightbox
    'autoplay' => false, // Auto advance in lightbox
    'autoplayInterval' => 5000, // Autoplay interval in ms
    'class' => '',
])

@php
    $gridCols = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 sm:grid-cols-2',
        3 => 'grid-cols-2 sm:grid-cols-3',
        4 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
        5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
    ];

    $gapClasses = [
        1 => 'gap-1',
        2 => 'gap-2',
        3 => 'gap-3',
        4 => 'gap-4',
        5 => 'gap-5',
        6 => 'gap-6',
        7 => 'gap-7',
        8 => 'gap-8',
    ];

    $aspectClasses = [
        'square' => 'aspect-square',
        'portrait' => 'aspect-[3/4]',
        'landscape' => 'aspect-[4/3]',
        'auto' => '',
    ];
@endphp

@if (!empty($images))
    <div x-data="imageGallery({{ json_encode([
        'images' => $images,
        'enableZoom' => $enableZoom,
        'autoplay' => $autoplay,
        'autoplayInterval' => $autoplayInterval,
    ]) }})" x-init="init()" class="{{ $class }} w-full">
        <!-- Thumbnail Grid -->
        <div class="{{ $gridCols[$columns] ?? $gridCols[4] }} {{ $gapClasses[$gap] ?? $gapClasses[4] }} grid">
            <template x-for="(image, index) in images" :key="index">
                <div @click="openLightbox(index)"
                    class="{{ $aspectClasses[$aspectRatio] ?? $aspectClasses['square'] }} group relative cursor-pointer overflow-hidden rounded-lg transition-all duration-300 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:scale-[1.02] hover:shadow-lg"
                    tabindex="0" :aria-label="`View image ${index + 1}: ${image.alt || 'Gallery image'}`"
                    @keydown.enter="openLightbox(index)" @keydown.space.prevent="openLightbox(index)">
                    <!-- Image -->
                    <img :src="image.thumbnail || image.url" :alt="image.alt || `Gallery image ${index + 1}`"
                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                        loading="lazy">

                    <!-- Overlay -->
                    <div
                        class="absolute inset-0 flex items-center justify-center bg-black/0 transition-all duration-300 group-hover:bg-black/20">
                        <div
                            class="rounded-full bg-white/90 p-3 opacity-0 backdrop-blur-sm transition-opacity duration-300 group-hover:opacity-100">
                            <svg class="h-6 w-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Caption (if enabled) -->
                    @if ($showCaptions)
                        <div x-show="image.caption"
                            class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                            <p class="truncate text-sm font-medium text-white" x-text="image.caption"></p>
                        </div>
                    @endif

                    <!-- Image Counter Badge -->
                    <div
                        class="absolute right-2 top-2 rounded-full bg-black/50 px-2 py-1 text-xs text-white backdrop-blur-sm">
                        <span x-text="index + 1"></span>/<span x-text="images.length"></span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Lightbox Modal -->
        <div x-show="isOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
            @keydown.escape.window="closeLightbox()" @keydown.left.window="previousImage()"
            @keydown.right.window="nextImage()" style="display: none;">
            <!-- Close Button -->
            <button @click="closeLightbox()"
                class="absolute right-4 top-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm transition-all duration-200 hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white"
                aria-label="Close gallery">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Image Container -->
            <div class="relative flex max-h-full w-full max-w-7xl items-center justify-center">
                <!-- Main Image -->
                <div class="relative max-h-full max-w-full" x-show="currentIndex !== null"
                    @wheel.prevent="handleZoom($event)">
                    <img x-show="currentIndex !== null" :src="currentIndex !== null ? images[currentIndex].url : ''"
                        :alt="currentIndex !== null ? (images[currentIndex].alt || `Gallery image ${currentIndex + 1}`) : ''"
                        class="max-h-[90vh] max-w-full select-none object-contain transition-transform duration-300"
                        :style="enableZoom ? `transform: scale(${zoomLevel}) translate(${panX}px, ${panY}px)` : ''"
                        @mousedown="startPan($event)" @mousemove="handlePan($event)" @mouseup="endPan()"
                        @mouseleave="endPan()" draggable="false">

                    <!-- Loading Indicator -->
                    <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-black/20">
                        <div class="h-8 w-8 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                    </div>
                </div>

                <!-- Navigation Arrows -->
                <template x-if="images.length > 1">
                    <div>
                        <!-- Previous Button -->
                        <button @click="previousImage()"
                            class="absolute left-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm transition-all duration-200 hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white"
                            :class="{ 'opacity-50 cursor-not-allowed': currentIndex === 0 && !canLoop() }"
                            aria-label="Previous image">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Next Button -->
                        <button @click="nextImage()"
                            class="absolute right-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm transition-all duration-200 hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white"
                            :class="{ 'opacity-50 cursor-not-allowed': currentIndex === images.length - 1 && !canLoop() }"
                            aria-label="Next image">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <!-- Bottom Info Bar -->
            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-6">
                <div class="mx-auto max-w-7xl">
                    <!-- Image Info -->
                    <div class="mb-4 flex items-center justify-between text-white">
                        <div>
                            <div class="flex items-center gap-4">
                                <span class="rounded-full bg-black/50 px-3 py-1 text-sm">
                                    <span x-text="currentIndex + 1"></span> of <span x-text="images.length"></span>
                                </span>
                                <template x-if="enableZoom && zoomLevel !== 1">
                                    <span class="rounded-full bg-black/50 px-3 py-1 text-sm">
                                        <span x-text="Math.round(zoomLevel * 100)"></span>%
                                    </span>
                                </template>
                            </div>
                            <div x-show="currentIndex !== null && images[currentIndex]?.caption" class="mt-2">
                                <p class="text-lg font-medium"
                                    x-text="currentIndex !== null ? images[currentIndex]?.caption : ''"></p>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="flex items-center gap-2">
                            <!-- Zoom Controls (if enabled) -->
                            <template x-if="enableZoom">
                                <div class="flex items-center gap-2">
                                    <button @click="zoomOut()" :disabled="zoomLevel <= 0.5"
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-all duration-200 hover:bg-black/70 disabled:cursor-not-allowed disabled:opacity-50"
                                        aria-label="Zoom out">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                                        </svg>
                                    </button>
                                    <button @click="resetZoom()"
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-all duration-200 hover:bg-black/70"
                                        aria-label="Reset zoom">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <button @click="zoomIn()" :disabled="zoomLevel >= 3"
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-all duration-200 hover:bg-black/70 disabled:cursor-not-allowed disabled:opacity-50"
                                        aria-label="Zoom in">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <!-- Autoplay Toggle -->
                            <template x-if="images.length > 1">
                                <button @click="toggleAutoplay()"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-all duration-200 hover:bg-black/70"
                                    :aria-label="isAutoplayActive ? 'Pause slideshow' : 'Start slideshow'">
                                    <svg x-show="!isAutoplayActive" class="h-5 w-5" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-9 5a7 7 0 1114 0H3z" />
                                    </svg>
                                    <svg x-show="isAutoplayActive" class="h-5 w-5" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Thumbnail Strip -->
                    <div
                        class="scrollbar-thin scrollbar-thumb-white/20 scrollbar-track-transparent flex gap-2 overflow-x-auto pb-2">
                        <template x-for="(image, index) in images" :key="index">
                            <button @click="goToImage(index)"
                                class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg border-2 transition-all duration-200"
                                :class="currentIndex === index ? 'border-white shadow-lg' :
                                    'border-transparent hover:border-white/50'">
                                <img :src="image.thumbnail || image.url" :alt="`Thumbnail ${index + 1}`"
                                    class="h-full w-full object-cover">
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function imageGallery(config) {
            return {
                images: config.images,
                isOpen: false,
                currentIndex: null,
                loading: false,
                enableZoom: config.enableZoom,
                zoomLevel: 1,
                panX: 0,
                panY: 0,
                isPanning: false,
                panStartX: 0,
                panStartY: 0,
                autoplay: config.autoplay,
                autoplayInterval: config.autoplayInterval,
                isAutoplayActive: false,
                autoplayTimer: null,

                init() {
                    // Preload first few images
                    this.preloadImages();
                },

                preloadImages() {
                    const imagesToPreload = this.images.slice(0, 5);
                    imagesToPreload.forEach(imageData => {
                        const img = new Image();
                        img.src = imageData.url;
                    });
                },

                openLightbox(index) {
                    this.currentIndex = index;
                    this.isOpen = true;
                    this.resetZoom();
                    this.loading = true;

                    // Preload current image
                    const img = new Image();
                    img.onload = () => {
                        this.loading = false;
                    };
                    img.src = this.images[index].url;

                    // Start autoplay if enabled
                    if (this.autoplay) {
                        this.startAutoplay();
                    }

                    // Prevent body scroll
                    document.body.style.overflow = 'hidden';
                },

                closeLightbox() {
                    this.isOpen = false;
                    this.currentIndex = null;
                    this.stopAutoplay();
                    this.resetZoom();

                    // Restore body scroll
                    document.body.style.overflow = '';
                },

                previousImage() {
                    if (this.currentIndex > 0) {
                        this.goToImage(this.currentIndex - 1);
                    } else if (this.canLoop()) {
                        this.goToImage(this.images.length - 1);
                    }
                },

                nextImage() {
                    if (this.currentIndex < this.images.length - 1) {
                        this.goToImage(this.currentIndex + 1);
                    } else if (this.canLoop()) {
                        this.goToImage(0);
                    }
                },

                goToImage(index) {
                    this.loading = true;
                    this.currentIndex = index;
                    this.resetZoom();

                    // Preload image
                    const img = new Image();
                    img.onload = () => {
                        this.loading = false;
                    };
                    img.src = this.images[index].url;

                    // Restart autoplay timer
                    if (this.isAutoplayActive) {
                        this.stopAutoplay();
                        this.startAutoplay();
                    }
                },

                canLoop() {
                    return true; // Always allow looping
                },

                // Zoom functionality
                zoomIn() {
                    if (this.zoomLevel < 3) {
                        this.zoomLevel = Math.min(3, this.zoomLevel + 0.5);
                    }
                },

                zoomOut() {
                    if (this.zoomLevel > 0.5) {
                        this.zoomLevel = Math.max(0.5, this.zoomLevel - 0.5);
                        if (this.zoomLevel === 1) {
                            this.panX = 0;
                            this.panY = 0;
                        }
                    }
                },

                resetZoom() {
                    this.zoomLevel = 1;
                    this.panX = 0;
                    this.panY = 0;
                },

                handleZoom(event) {
                    if (!this.enableZoom) return;

                    event.preventDefault();
                    const delta = event.deltaY > 0 ? -0.1 : 0.1;
                    const newZoom = Math.min(3, Math.max(0.5, this.zoomLevel + delta));

                    if (newZoom !== this.zoomLevel) {
                        this.zoomLevel = newZoom;
                        if (newZoom === 1) {
                            this.panX = 0;
                            this.panY = 0;
                        }
                    }
                },

                // Pan functionality
                startPan(event) {
                    if (this.zoomLevel > 1) {
                        this.isPanning = true;
                        this.panStartX = event.clientX - this.panX;
                        this.panStartY = event.clientY - this.panY;
                    }
                },

                handlePan(event) {
                    if (this.isPanning && this.zoomLevel > 1) {
                        this.panX = event.clientX - this.panStartX;
                        this.panY = event.clientY - this.panStartY;
                    }
                },

                endPan() {
                    this.isPanning = false;
                },

                // Autoplay functionality
                startAutoplay() {
                    this.isAutoplayActive = true;
                    this.autoplayTimer = setInterval(() => {
                        this.nextImage();
                    }, this.autoplayInterval);
                },

                stopAutoplay() {
                    this.isAutoplayActive = false;
                    if (this.autoplayTimer) {
                        clearInterval(this.autoplayTimer);
                        this.autoplayTimer = null;
                    }
                },

                toggleAutoplay() {
                    if (this.isAutoplayActive) {
                        this.stopAutoplay();
                    } else {
                        this.startAutoplay();
                    }
                }
            }
        }
    </script>
@else
    <!-- Empty State -->
    <div
        class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-12 text-center">
        <svg class="mb-4 h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        <h3 class="mb-2 text-lg font-semibold text-gray-900">No images found</h3>
        <p class="text-gray-500">Add some images to display in the gallery.</p>
    </div>
@endif
