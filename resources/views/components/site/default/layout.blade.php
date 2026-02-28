@props(['metaTitle', 'metaDescription', 'metaKeywords'])

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="keywords" content="{{ $metaKeywords }}">
    <meta name="author" content="Anohim">
    <title>{{ $metaTitle ?? config('config.general.app_name', config('app.name', 'Anohim')) }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="icon" href="{{ config('config.assets.favicon') }}" type="image/png">

    @vite(['resources/js/site.js', 'resources/css/site.css'], 'site/build')
    @livewireStyles
</head>

<body class="theme-{{ config('config.site.color_scheme', 'default') }}">

    <x-site.header />

    {{ $slot }}

    <x-site.footer />

    <div class="fixed bottom-40 right-6 z-50 hidden flex-col gap-3 md:flex">
        @if (config('config.social_network.facebook'))
            <a href="{{ config('config.social_network.facebook') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-[#1877F2] text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-[#0d6efd]">
                <i class="fa-brands fa-facebook-f"></i>
            </a>
        @endif
        @if (config('config.social_network.twitter'))
            <a href="{{ config('config.social_network.twitter') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-[#1DA1F2] text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-[#0d95e8]">
                <i class="fa-brands fa-twitter"></i>
            </a>
        @endif
        @if (config('config.social_network.linkedin'))
            <a href="{{ config('config.social_network.linkedin') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-[#0A66C2] text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-[#094c8f]">
                <i class="fa-brands fa-linkedin-in"></i>
            </a>
        @endif
        @if (config('config.social_network.youtube'))
            <a href="{{ config('config.social_network.youtube') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-[#FF0000] text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-[#cc0000]">
                <i class="fa-brands fa-youtube"></i>
            </a>
        @endif
        @if (config('config.social_network.google'))
            <a href="{{ config('config.social_network.google') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-[#4285F4] text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-[#3367d6]">
                <i class="fa-brands fa-google"></i>
            </a>
        @endif
        @if (config('config.social_network.github'))
            <a href="{{ config('config.social_network.github') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-[#333333] text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-[#24292e]">
                <i class="fa-brands fa-github"></i>
            </a>
        @endif
        @if (config('config.general.app_phone'))
            <a href="tel:{{ config('config.general.app_phone') }}"
                class="bg-site-primary hover:bg-site-dark-primary flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 text-white transition-all duration-200 hover:scale-110 hover:border-white/40">
                <i class="fa-solid fa-phone"></i>
            </a>
        @endif
        @if (config('config.general.app_email'))
            <a href="mailto:{{ config('config.general.app_email') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-black text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-gray-900">
                <i class="fa-solid fa-envelope"></i>
            </a>
        @endif
        @if (config('config.general.app_email'))
            <a href="https://web.whatsapp.com/send?phone={{ config('config.general.app_phone') }}"
                class="flex h-12 w-12 transform items-center justify-center rounded-full border-2 border-white/20 bg-green-800 text-white transition-all duration-200 hover:scale-110 hover:border-white/40 hover:bg-green-900">
                <i class="fa-brands fa-whatsapp"></i>
            </a>
        @endif
    </div>

    @livewireScriptConfig
</body>

</html>
