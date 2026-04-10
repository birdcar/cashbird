<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Debt</h1>
        <a href="{{ route('debt.create') }}" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800" wire:navigate>
            Add Debt
        </a>
    </div>

    @if(!$hasDebts)
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="mb-4 text-gray-600">No debts tracked yet. Add your debts to start building a payoff plan.</p>
            <a href="{{ route('debt.create') }}" class="inline-block rounded-lg bg-gray-900 px-6 py-3 text-sm font-medium text-white hover:bg-gray-800" wire:navigate>
                Add Your First Debt
            </a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-500">Total Owed</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($totalOwed / 100, 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-500">Average APR</p>
                <p class="text-2xl font-bold text-gray-900">{{ $avgApr }}%</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-500">Monthly Minimums</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($totalMinimum / 100, 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-500">Debt-Free Date</p>
                <p class="text-2xl font-bold text-gray-900">
                    @if($schedule->monthsToDebtFree > 0)
                        {{ $schedule->projectedDebtFreeDate->format('M Y') }}
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        <livewire:debt.payoff-timeline />

        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Debts (by APR, highest first)</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($debts as $debt)
                    <a href="{{ route('debt.show', $debt) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50" wire:navigate>
                        <div>
                            <p class="font-medium text-gray-900">{{ $debt->name }}</p>
                            <p class="text-sm text-gray-500">{{ $debt->lender ?? ucfirst(str_replace('_', ' ', $debt->type)) }} &middot; {{ number_format((float) $debt->apr, 2) }}% APR</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-gray-900">${{ number_format($debt->current_balance / 100, 2) }}</p>
                            <p class="text-sm text-gray-500">${{ number_format($debt->minimum_payment / 100, 2) }}/mo min</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
