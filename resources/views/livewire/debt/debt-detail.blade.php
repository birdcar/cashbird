<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('debt.index') }}" class="text-gray-500 hover:text-gray-700" wire:navigate>&larr; Back</a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $debt->name }}</h1>
        @if($debt->is_in_recovery)
            <span class="rounded bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">Recovery Plan</span>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-500">Current Balance</p>
            <p class="text-2xl font-bold text-gray-900">${{ number_format($debt->current_balance / 100, 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-500">APR</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format((float) $debt->apr, 2) }}%</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-500">Minimum Payment</p>
            <p class="text-2xl font-bold text-gray-900">${{ number_format($debt->minimum_payment / 100, 2) }}/mo</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-500">Projected Payoff</p>
            <p class="text-2xl font-bold text-gray-900">
                @if($schedule->monthsToDebtFree > 0)
                    {{ $schedule->projectedDebtFreeDate->format('M Y') }}
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    @if($debt->original_balance)
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-gray-500">Progress</span>
                <span class="font-medium text-gray-900">
                    @php $paidPct = $debt->original_balance > 0 ? round(($debt->original_balance - $debt->current_balance) / $debt->original_balance * 100) : 0 @endphp
                    {{ $paidPct }}% paid off
                </span>
            </div>
            <div class="h-3 w-full rounded-full bg-gray-100">
                <div class="h-3 rounded-full bg-gray-800" style="width: {{ min(100, $paidPct) }}%"></div>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Payoff Scenarios</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($scenarios as $scenario)
                <div class="flex items-center justify-between px-6 py-3 text-sm">
                    <span class="text-gray-600">
                        @if($scenario['extra'] === 0)
                            Minimum only
                        @else
                            +${{ number_format($scenario['extra'] / 100, 2) }}/mo extra
                        @endif
                    </span>
                    <div class="flex gap-8">
                        <span class="text-gray-900">{{ $scenario['months'] }} months</span>
                        <span class="text-gray-500">${{ number_format($scenario['total_interest'] / 100, 2) }} interest</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Payment History</h2>
        </div>
        @if($payments->isEmpty())
            <div class="p-6 text-center text-gray-500">No payments recorded yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($payments as $payment)
                    <div class="flex items-center justify-between px-6 py-3 text-sm">
                        <span class="text-gray-600">{{ $payment->payment_date->format('M j, Y') }}</span>
                        <div class="flex items-center gap-4">
                            <span class="font-medium text-gray-900">${{ number_format($payment->amount / 100, 2) }}</span>
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $payment->source }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
