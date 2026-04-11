<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' — Cashbird' : 'Cashbird' }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|fraunces:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-sand-50 text-sand-900 antialiased">
    <div x-data="{
        sidebarOpen: false,
        awaitingG: false,
        showShortcuts: false,
        showCommandPalette: false,
        commandSearch: '',
    }"
    @keydown.window="
        if (($event.metaKey || $event.ctrlKey) && $event.key === 'k') { showCommandPalette = !showCommandPalette; commandSearch = ''; $event.preventDefault(); return; }
        if ($event.target.tagName === 'INPUT' || $event.target.tagName === 'SELECT' || $event.target.tagName === 'TEXTAREA' || $event.target.isContentEditable) return;
        if ($event.key === '?') { showShortcuts = !showShortcuts; $event.preventDefault(); return; }
        if ($event.key === 'Escape') { if (showCommandPalette) { showCommandPalette = false; return; } showShortcuts = false; return; }
        if (awaitingG) {
            awaitingG = false;
            const routes = { d: '{{ route('dashboard') }}', b: '{{ route('budget.index') }}', t: '{{ route('transactions.index') }}', e: '{{ route('debt.index') }}', r: '{{ route('reports.index') }}', i: '{{ route('insights.index') }}', c: '{{ route('chat.index') }}', s: '{{ route('sharing.index') }}', a: '{{ route('accounts.index') }}' };
            if (routes[$event.key]) { window.location.href = routes[$event.key]; $event.preventDefault(); }
            return;
        }
        if ($event.key === 'g') { awaitingG = true; setTimeout(() => awaitingG = false, 1000); return; }
    "
    class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:leave="transition-opacity ease-linear duration-200" x-cloak class="fixed inset-0 z-30 bg-sand-900/40 lg:hidden" @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 lg:static lg:translate-x-0">
            @include('livewire.layout.sidebar')
        </div>

        {{-- Main content --}}
        <main class="flex-1 px-4 py-6 lg:px-10 lg:py-8">
            {{-- Mobile header --}}
            <div class="mb-6 flex items-center lg:hidden">
                <button @click="sidebarOpen = true" class="rounded-lg p-2 text-sand-500 hover:bg-sand-100 hover:text-sand-800" aria-label="Open navigation menu">
                    <x-phosphor-list class="h-6 w-6" />
                </button>
                <span class="ml-3 font-display text-xl font-bold text-sand-900">Cashbird</span>
            </div>

            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, {{ session('undo_route') ? 8000 : 4000 }})" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="mb-6 flex items-center gap-3 rounded-xl bg-sage-50 border border-sage-200 px-4 py-3 text-sm text-sage-800">
                    <x-phosphor-check-circle-fill class="h-5 w-5 shrink-0 text-sage-500" />
                    <span class="flex-1">
                        {{ session('success') }}
                        @if(session('undo_route'))
                            <form method="POST" action="{{ session('undo_route') }}" class="ml-2 inline">
                                @csrf
                                <button type="submit" class="font-medium text-amber-600 underline underline-offset-2 transition-colors hover:text-amber-700">Undo</button>
                            </form>
                        @endif
                    </span>
                    <button @click="show = false" class="shrink-0 text-sage-400 transition-colors hover:text-sage-600" aria-label="Dismiss">
                        <x-phosphor-x class="h-4 w-4" />
                    </button>
                </div>
            @endif

            {{ $slot }}
        </main>

        {{-- Keyboard shortcuts --}}
        <template x-if="showShortcuts">
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-sand-900/40" @click.self="showShortcuts = false" @keydown.escape.window="showShortcuts = false">
                <div class="w-full max-w-sm rounded-xl border border-sand-200 bg-white p-6 shadow-xl" @click.stop>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-lg font-semibold text-sand-900">Keyboard shortcuts</h2>
                        <button @click="showShortcuts = false" class="text-sand-400 hover:text-sand-600">
                            <x-phosphor-x class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="space-y-2 text-sm">
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-sand-400">Navigation (press g then...)</p>
                        <div class="flex justify-between"><span class="text-sand-600">Dashboard</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g d</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Budget</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g b</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Transactions</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g t</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Debt</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g e</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Reports</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g r</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Insights</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g i</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Ask Cashbird</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g c</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Accounts</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g a</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Sharing</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">g s</kbd></div>
                        <div class="mt-3 border-t border-sand-100 pt-3"></div>
                        <div class="flex justify-between"><span class="text-sand-600">Command palette</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">⌘K</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Show shortcuts</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">?</kbd></div>
                        <div class="flex justify-between"><span class="text-sand-600">Close</span><kbd class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-700">Esc</kbd></div>
                    </div>
                    <p class="mt-4 border-t border-sand-100 pt-3 text-xs text-sand-400">Press <kbd class="rounded bg-sand-100 px-1 py-0.5 font-mono">?</kbd> anywhere to see this</p>
                </div>
            </div>
        </template>

        {{-- Command palette --}}
        <template x-if="showCommandPalette">
            <div class="fixed inset-0 z-50 flex items-start justify-center pt-[20vh] bg-sand-900/40" @click.self="showCommandPalette = false">
                <div class="w-full max-w-lg rounded-xl border border-sand-200 bg-white shadow-2xl overflow-hidden"
                     x-data="{
                         search: '',
                         selectedIndex: 0,
                         commands: [
                             { label: 'Dashboard', url: '{{ route('dashboard') }}', shortcut: 'g d' },
                             { label: 'Budget', url: '{{ route('budget.index') }}', shortcut: 'g b' },
                             { label: 'Transactions', url: '{{ route('transactions.index') }}', shortcut: 'g t' },
                             { label: 'Accounts', url: '{{ route('accounts.index') }}', shortcut: 'g a' },
                             { label: 'Debt', url: '{{ route('debt.index') }}', shortcut: 'g e' },
                             { label: 'Reports', url: '{{ route('reports.index') }}', shortcut: 'g r' },
                             { label: 'Insights', url: '{{ route('insights.index') }}', shortcut: 'g i' },
                             { label: 'Ask Cashbird', url: '{{ route('chat.index') }}', shortcut: 'g c' },
                             { label: 'Sharing', url: '{{ route('sharing.index') }}', shortcut: 'g s' },
                             { label: 'Connect Account', url: '{{ route('accounts.connect') }}', shortcut: '' },
                             { label: 'Add Debt', url: '{{ route('debt.create') }}', shortcut: '' },
                         ],
                         get filtered() {
                             if (!this.search) return this.commands;
                             const s = this.search.toLowerCase();
                             return this.commands.filter(c => c.label.toLowerCase().includes(s));
                         },
                         go(url) { window.location.href = url; },
                         handleKey(e) {
                             if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, this.filtered.length - 1); }
                             else if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, 0); }
                             else if (e.key === 'Enter' && this.filtered[this.selectedIndex]) { this.go(this.filtered[this.selectedIndex].url); }
                         }
                     }"
                     x-init="$nextTick(() => $refs.cmdInput.focus())"
                     @keydown="handleKey($event)"
                     @click.stop>

                    {{-- Search input --}}
                    <div class="flex items-center gap-3 border-b border-sand-100 px-4 py-3">
                        <x-phosphor-magnifying-glass class="h-5 w-5 shrink-0 text-sand-400" />
                        <input x-ref="cmdInput" x-model="search" @input="selectedIndex = 0" type="text" placeholder="Search or jump to..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-400 focus:outline-none focus:ring-0" />
                        <kbd class="shrink-0 rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-500">Esc</kbd>
                    </div>

                    {{-- Results --}}
                    <div class="max-h-72 overflow-y-auto py-2">
                        <template x-for="(cmd, i) in filtered" :key="cmd.label">
                            <button @click="go(cmd.url)" @mouseenter="selectedIndex = i"
                                    :class="i === selectedIndex ? 'bg-amber-50 text-amber-900' : 'text-sand-700'"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-sm transition-colors">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="i === selectedIndex ? 'bg-amber-100' : 'bg-sand-100'">
                                    <span class="text-xs font-semibold" :class="i === selectedIndex ? 'text-amber-600' : 'text-sand-500'" x-text="cmd.label.charAt(0)"></span>
                                </span>
                                <span class="flex-1 text-left" x-text="cmd.label"></span>
                                <kbd x-show="cmd.shortcut" x-text="cmd.shortcut" class="rounded bg-sand-100 px-1.5 py-0.5 font-mono text-xs text-sand-500"></kbd>
                            </button>
                        </template>
                        <div x-show="filtered.length === 0" class="px-4 py-6 text-center text-sm text-sand-400">
                            No results found
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center gap-4 border-t border-sand-100 px-4 py-2 text-xs text-sand-400">
                        <span><kbd class="rounded bg-sand-100 px-1 py-0.5 font-mono">↑↓</kbd> navigate</span>
                        <span><kbd class="rounded bg-sand-100 px-1 py-0.5 font-mono">↵</kbd> open</span>
                        <span><kbd class="rounded bg-sand-100 px-1 py-0.5 font-mono">esc</kbd> close</span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
