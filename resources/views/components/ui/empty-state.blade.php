@props([
    'title' => 'No data found',
    'description' => 'There are no records to display at this time.',
    'icon' => 'default',
    'customIcon' => null,
    'illustration' => false,
    'size' => 'default',
    'animated' => true,
    'class' => '',
])

@php
    $sizeClasses = [
        'small' => 'py-8 px-4',
        'default' => 'py-12 px-6',
        'large' => 'py-16 px-8',
    ];

    $iconSizes = [
        'small' => 'w-12 h-12',
        'default' => 'w-16 h-16',
        'large' => 'w-20 h-20',
    ];

    $illustrationSizes = [
        'small' => 'w-32 h-32',
        'default' => 'w-48 h-48',
        'large' => 'w-64 h-64',
    ];

    $icons = [
        'default' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>',
    ];
@endphp

<div class="{{ $sizeClasses[$size] ?? $sizeClasses['default'] }} {{ $class }} flex flex-col items-center justify-center text-center"
    @if ($animated) x-data="{ show: false }"
        x-init="setTimeout(() => show = true, 100)"
        x-show="show"
        x-transition:enter="transition ease-out duration-500"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100" @endif
    role="status" aria-live="polite">
    <!-- Illustration or Icon -->
    <div class="relative mb-6">
        @if ($illustration)
            <!-- Modern Illustration -->
            <div class="{{ $illustrationSizes[$size] ?? $illustrationSizes['default'] }} relative mx-auto">
                <svg viewBox="0 0 200 200" class="h-full w-full">
                    <!-- Background Circle -->
                    <circle cx="100" cy="100" r="80" fill="#f3f4f6" opacity="0.5" />
                    <circle cx="100" cy="100" r="60" fill="#e5e7eb" opacity="0.3" />

                    <!-- Empty State Illustration -->
                    <g transform="translate(100,100)">
                        <!-- Folder/Container -->
                        <rect x="-25" y="-10" width="50" height="35" rx="3" fill="#d1d5db"
                            opacity="0.8" />
                        <rect x="-20" y="-5" width="40" height="25" rx="2" fill="white" />

                        <!-- Floating Elements -->
                        <circle cx="-15" cy="-35" r="3" fill="#6366f1" opacity="0.6">
                            @if ($animated)
                                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-5; 0,0"
                                    dur="3s" repeatCount="indefinite" />
                            @endif
                        </circle>
                        <circle cx="15" cy="-30" r="2" fill="#8b5cf6" opacity="0.6">
                            @if ($animated)
                                <animateTransform attributeName="transform" type="translate" values="0,0; 0,3; 0,0"
                                    dur="2s" repeatCount="indefinite" />
                            @endif
                        </circle>
                        <circle cx="0" cy="-45" r="1.5" fill="#06b6d4" opacity="0.6">
                            @if ($animated)
                                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-3; 0,0"
                                    dur="4s" repeatCount="indefinite" />
                            @endif
                        </circle>
                    </g>
                </svg>
            </div>
        @else
            <!-- Icon -->
            <div class="relative">
                <div
                    class="{{ $iconSizes[$size] ?? $iconSizes['default'] }} absolute inset-0 rounded-full bg-gradient-to-r from-indigo-100 to-purple-100 opacity-20">
                </div>
                <div
                    class="{{ $iconSizes[$size] ?? $iconSizes['default'] }} relative mx-auto flex items-center justify-center rounded-full border-2 border-gray-200 bg-gray-50 text-gray-400">
                    @if ($customIcon)
                        {!! $customIcon !!}
                    @else
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-2/3 w-2/3">
                            {!! $icons[$icon] ?? $icons['default'] !!}
                        </svg>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Content -->
    <div class="mx-auto max-w-sm">
        <h3 class="mb-2 text-lg font-semibold text-gray-900">
            {{ $title }}
        </h3>

        <p class="mb-6 text-sm leading-relaxed text-gray-500">
            {{ $description }}
        </p>
    </div>

    <!-- Help Text -->
    <div class="mt-4 text-xs text-gray-400">
        Need help? <a href="#" class="text-primary hover:text-dark-primary font-medium">Contact Us</a>
    </div>
</div>
