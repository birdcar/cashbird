<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">Debt</h1>
        <a href="{{ route('debt.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600" wire:navigate>
            <x-phosphor-plus-circle class="h-4 w-4" />
            Add Debt
        </a>
    </div>

    @if(!$hasDebts)
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-trend-down class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="mb-4 text-sand-600">No debts added yet. Track yours to see a payoff plan.</p>
            <a href="{{ route('debt.create') }}" class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600" wire:navigate>
                Add a debt
            </a>
        </div>
        @if($savingsStage)
            <div class="flex items-center gap-3 rounded-lg bg-sage-50 border border-sage-200 px-4 py-3">
                <x-phosphor-piggy-bank class="h-5 w-5 shrink-0 text-sage-600" />
                <p class="text-sm text-sage-800">
                    All debts cleared! Your next step:
                    <a href="{{ route('savings.index') }}" class="font-medium underline hover:text-sage-900">
                        @if($savingsStage->value === 'named_goals')
                            start saving for your goals
                        @else
                            build your full emergency fund
                        @endif
                    </a>.
                </p>
            </div>
        @endif
    @else
        {{-- Stats floating free --}}
        <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Total debt</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($totalOwed / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Avg. interest rate <x-help-tip text="The average annual interest rate across all your debts, weighted by balance." /></p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">{{ $avgApr }}%</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Minimum payments</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($totalMinimum / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Debt-free by</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">
                    @if($schedule->monthsToDebtFree > 0)
                        {{ $schedule->projectedDebtFreeDate->format('M Y') }}
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        <livewire:debt.payoff-timeline />

        {{-- Debt list — keep card since it's an interactive list --}}
        <div class="rounded-xl border border-sand-200 bg-white">
            <div class="border-b border-sand-100 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-sand-900">Your debts (highest interest first)</h2>
            </div>
            <div class="divide-y divide-sand-100">
                @foreach($debts as $debt)
                    <a wire:key="debt-{{ $debt->id }}" href="{{ route('debt.show', $debt) }}" class="flex items-center justify-between px-6 py-4 transition-colors hover:bg-sand-50" wire:navigate>
                        <div>
                            <p class="font-medium text-sand-900">{{ $debt->name }}</p>
                            <p class="text-sm text-sand-500">{{ $debt->lender ?? ucfirst(str_replace('_', ' ', $debt->type)) }} &middot; {{ number_format((float) $debt->apr, 2) }}% interest rate</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-sand-900">${{ number_format($debt->current_balance / 100, 2) }}</p>
                            <p class="text-sm text-sand-500">${{ number_format($debt->minimum_payment / 100, 2) }}/mo min</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
