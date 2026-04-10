<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Cashbird') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:leave="transition-opacity ease-linear duration-200" x-cloak class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 lg:static lg:translate-x-0">
            @include('livewire.layout.sidebar')
        </div>

        {{-- Main content --}}
        <main class="flex-1 p-4 lg:p-8">
            {{-- Mobile header --}}
            <div class="mb-4 flex items-center lg:hidden">
                <button @click="sidebarOpen = true" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900" aria-label="Open navigation menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
                <span class="ml-3 text-lg font-bold text-gray-900">Cashbird</span>
            </div>

            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
