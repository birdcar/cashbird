<nav aria-label="Main navigation" class="flex h-full w-64 flex-col bg-sand-100 border-r border-sand-200">
    <div class="flex h-16 items-center justify-between border-b border-sand-200 px-6">
        <span class="font-display text-xl font-bold text-sand-900">Cashbird</span>
        <button @click="sidebarOpen = false" class="rounded-lg p-1 text-sand-400 hover:text-sand-700 lg:hidden" aria-label="Close navigation menu">
            <x-phosphor-x class="h-5 w-5" />
        </button>
    </div>

    @php
        $rts = app(\App\Services\Budget\ReadyToSpend::class)->compute(auth()->id());
        $dailySafe = collect($rts)->sum('daily_safe');
        $hasBudgetData = ! empty($rts);
    @endphp
    @if($hasBudgetData)
        <div class="border-b border-sand-200 px-6 py-3">
            <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Safe to spend</p>
            <p class="font-display text-lg font-semibold text-sand-900">${{ number_format($dailySafe / 100, 2) }}</p>
        </div>
    @endif

    <div class="flex flex-1 flex-col gap-1 p-4">
        <a href="{{ route('dashboard') }}" {{ request()->routeIs('dashboard') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-house-simple{{ request()->routeIs('dashboard') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Dashboard
        </a>
        <a href="{{ route('accounts.index') }}" {{ request()->routeIs('accounts.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('accounts.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-bank{{ request()->routeIs('accounts.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Accounts
        </a>
        <a href="{{ route('budget.index') }}" {{ request()->routeIs('budget.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('budget.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-chart-pie-slice{{ request()->routeIs('budget.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Budget
        </a>
        <a href="{{ route('transactions.index') }}" {{ request()->routeIs('transactions.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('transactions.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-arrows-left-right{{ request()->routeIs('transactions.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Transactions
        </a>
        <a href="{{ route('debt.index') }}" {{ request()->routeIs('debt.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('debt.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-trend-down{{ request()->routeIs('debt.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Debt
        </a>
        <a href="{{ route('net-worth.index') }}" {{ request()->routeIs('net-worth.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('net-worth.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-chart-line{{ request()->routeIs('net-worth.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Net Worth
        </a>

        <div class="my-2 border-t border-sand-200"></div>

        <a href="{{ route('reports.index') }}" {{ request()->routeIs('reports.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('reports.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-file-text{{ request()->routeIs('reports.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Reports
        </a>
        <a href="{{ route('insights.index') }}" {{ request()->routeIs('insights.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('insights.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-lightbulb{{ request()->routeIs('insights.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Insights
        </a>
        <a href="{{ route('chat.index') }}" {{ request()->routeIs('chat.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('chat.*') ? 'bg-amber-100 text-amber-900' : 'text-amber-600 hover:bg-amber-50 hover:text-amber-700' }}">
            <x-phosphor-chat-circle-text{{ request()->routeIs('chat.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Ask Cashbird
        </a>
    </div>

    <div class="border-t border-sand-200 p-4">
        <a href="{{ route('sharing.index') }}" {{ request()->routeIs('sharing.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('sharing.*') ? 'bg-amber-100 text-amber-900' : 'text-sand-600 hover:bg-sand-200 hover:text-sand-900' }}">
            <x-phosphor-users-three{{ request()->routeIs('sharing.*') ? '-fill' : '' }} class="h-5 w-5 shrink-0" />
            Sharing
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-sand-500 transition-colors hover:bg-sand-200 hover:text-sand-700">
                <x-phosphor-sign-out class="h-5 w-5 shrink-0" />
                Sign out
            </button>
        </form>
    </div>
</nav>
