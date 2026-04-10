<nav aria-label="Main navigation" class="flex h-full w-64 flex-col border-r border-gray-200 bg-white">
    <div class="flex h-16 items-center justify-between border-b border-gray-200 px-6">
        <span class="text-lg font-bold text-gray-900">Cashbird</span>
        <button @click="sidebarOpen = false" class="rounded-lg p-1 text-gray-400 hover:text-gray-600 lg:hidden" aria-label="Close navigation menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-4">
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
            Dashboard
        </a>
        <a href="{{ route('accounts.index') }}"
           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('accounts.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
            Accounts
        </a>
        <a href="{{ route('budget.index') }}"
           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('budget.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
            Budget
        </a>
        <a href="{{ route('debt.index') }}"
           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('debt.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
            Debt
        </a>
        <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-400" title="Coming soon">
            Reports <span class="text-xs">(soon)</span>
        </span>
    </div>

    <div class="border-t border-gray-200 p-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                Logout
            </button>
        </form>
    </div>
</nav>
