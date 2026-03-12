<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Governance Panel' }}</title>
    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen">
        <nav class="bg-indigo-700 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center">
                        <span class="font-bold text-xl tracking-tight">SaaS Governance</span>
                    </div>
                    @auth('central')
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="{{ route('central.dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('central.dashboard') ? 'bg-indigo-900' : 'hover:bg-indigo-600' }}">Dashboard</a>
                            
                            @if(auth('central')->user()?->isPlatformOwner() || auth('central')->user()?->isMinistryAdmin())
                                <a href="{{ route('central.ministries') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('central.ministries') ? 'bg-indigo-900' : 'hover:bg-indigo-600' }}">Ministries</a>
                            @endif

                            <a href="{{ route('central.schools') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('central.schools') ? 'bg-indigo-900' : 'hover:bg-indigo-600' }}">Schools</a>
                            
                            <a href="{{ route('central.users') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('central.users') ? 'bg-indigo-900' : 'hover:bg-indigo-600' }}">Users</a>
                            
                            @if(auth('central')->user()?->isPlatformOwner())
                                <a href="{{ route('central.roles.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('central.roles.index') ? 'bg-indigo-900' : 'hover:bg-indigo-600' }}">Roles & Permissions</a>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm mr-4">{{ auth('central')->user()?->name }}</span>
                        <a href="{{ route('central.logout') }}" class="text-sm font-medium hover:underline">Logout</a>
                    </div>
                    @endauth
                </div>
            </div>
        </nav>

        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ $header ?? 'Dashboard' }}
                </h1>
            </div>
        </header>

        <main>
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
