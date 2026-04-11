<div class="space-y-8">
    <div class="flex items-center gap-4">
        <a href="{{ route('debt.index') }}" class="text-sand-400 transition-colors hover:text-sand-700" wire:navigate aria-label="Back to debt list">
            <x-phosphor-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">{{ $debt->name }}</h1>
        @if($debt->is_in_recovery)
            <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Recovery Plan</span>
        @endif
    </div>

    {{-- Stats — floating on background, no card wrappers --}}
    <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Current balance</p>
            <p class="mt-1 truncate font-display text-2xl font-semibold text-sand-900">${{ number_format($debt->current_balance / 100, 2) }}</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Interest rate <x-help-tip text="The annual percentage rate (APR) charged on your balance. Lower is better." /></p>
            <p class="mt-1 font-display text-2xl font-semibold text-sand-900">{{ number_format((float) $debt->apr, 2) }}%</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Minimum payment</p>
            <p class="mt-1 truncate font-display text-2xl font-semibold text-sand-900">${{ number_format($debt->minimum_payment / 100, 2) }}/mo</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Paid off by</p>
            <p class="mt-1 font-display text-2xl font-semibold text-sand-900">
                @if($schedule->monthsToDebtFree > 0)
                    {{ $schedule->projectedDebtFreeDate->format('M Y') }}
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    @if($debt->original_balance)
        @php $paidPct = $debt->original_balance > 0 ? (int) round(($debt->original_balance - $debt->current_balance) / $debt->original_balance * 100) : 0 @endphp
        <div>
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-sand-500">Progress</span>
                <span class="font-medium text-sand-900">{{ $paidPct }}% paid off</span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-sand-100"
                 role="progressbar"
                 aria-valuenow="{{ min(100, $paidPct) }}"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-label="Debt payoff progress: {{ $paidPct }}% paid off">
                <div class="h-2.5 rounded-full bg-sage-500 transition-all duration-500" style="width: {{ min(100, $paidPct) }}%"></div>
            </div>
        </div>
    @endif

    {{-- Scenarios — subtle background, no heavy border --}}
    <div>
        <h2 class="mb-4 font-display text-lg font-semibold text-sand-900">What-if scenarios <x-help-tip text="See how paying extra each month affects your payoff date and total interest." /></h2>
        <div class="divide-y divide-sand-100 rounded-xl bg-sand-100/50">
            @foreach($scenarios as $scenario)
                <div wire:key="scenario-{{ $scenario['extra'] }}" class="flex flex-col gap-1 px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                    <span class="text-sand-600">
                        @if($scenario['extra'] === 0)
                            Minimum only
                        @else
                            +${{ number_format($scenario['extra'] / 100, 2) }}/mo extra
                        @endif
                    </span>
                    <div class="flex gap-4 sm:gap-8">
                        <span class="font-medium text-sand-900">{{ $scenario['months'] }} months</span>
                        <span class="text-sand-500">${{ number_format($scenario['total_interest'] / 100, 2) }} interest</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Payment history — keep card for interactive list content --}}
    <div>
        <h2 class="mb-4 font-display text-lg font-semibold text-sand-900">Payment history</h2>
        @if($payments->isEmpty())
            <div class="py-8 text-center">
                <x-phosphor-receipt class="mx-auto mb-2 h-8 w-8 text-sand-300" />
                <p class="text-sand-500">No payments recorded yet.</p>
            </div>
        @else
            <div class="divide-y divide-sand-100 rounded-xl bg-sand-100/50">
                @foreach($payments as $payment)
                    <div wire:key="{{ $payment->id }}" class="flex items-center justify-between px-5 py-3 text-sm">
                        <span class="text-sand-500">{{ $payment->payment_date->format('M j, Y') }}</span>
                        <div class="flex items-center gap-4">
                            <span class="font-medium text-sand-900">${{ number_format($payment->amount / 100, 2) }}</span>
                            <span class="rounded bg-sand-200 px-2 py-0.5 text-xs text-sand-600">{{ $payment->source }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
