<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ config('config.general.meta_description') }}">
    <meta name="keywords" content="{{ config('config.general.meta_keywords') }}">
    <meta name="author" content="{{ config('config.general.meta_author') }}">
    <title>{{ config('config.general.app_name', config('app.name', 'ScriptMint')) }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="icon" href="{{ config('config.assets.favicon') }}" type="image/png">

    @vite(['resources/js/site.js', 'resources/css/site.css'], 'site/build')
</head>

<body class="{{ config('config.layout.display') }} theme-{{ config('config.system.color_scheme', 'default') }}">
    <div class="flex min-h-screen items-center overflow-hidden bg-gray-800">
        <div class="mx-auto w-full max-w-screen-xl px-4 py-4 sm:px-6">
            <div class="rounded-lg bg-gray-200 px-4 py-16 sm:px-6 sm:py-24 md:grid md:place-items-center lg:px-8">
                <div class="mx-auto max-w-max">
                    <main>
                        {{ $slot }}
                    </main>

                    <div class="mt-6 flex justify-center">
                        <a class="flex items-center gap-2 text-sm text-gray-800" href="{{ $url }}"><i
                                class="fa-solid fa-arrow-right"></i>
                            {{ $actionText }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
