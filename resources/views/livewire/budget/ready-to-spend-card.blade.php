<div class="rounded-2xl border border-amber-200 bg-amber-50 p-8">
    <p class="mb-1 text-sm font-medium tracking-wide text-amber-700 uppercase">Safe to Spend Today</p>

    @if($hasData)
        <div class="mt-2 mb-6">
            <p class="font-display text-fluid-xl font-semibold text-sand-900">
                ${{ number_format($totalDailySafe / 100, 2) }}
            </p>
            <p class="mt-1 text-sm text-sand-500">per day for the rest of this month <x-help-tip text="Your remaining budget divided by the days left this month." /></p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 border-t border-amber-200 pt-6">
            <div>
                <p class="text-xs font-medium text-amber-700 uppercase tracking-wide">Left this month</p>
                <p class="mt-1 text-2xl font-semibold {{ $totalRemaining >= 0 ? 'text-sand-900' : 'text-terracotta-600' }}">${{ number_format($totalRemaining / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-amber-700 uppercase tracking-wide">Budget used <x-help-tip text="How much of your total monthly budget has been spent so far." /></p>
                <p class="mt-1 text-2xl font-semibold text-sand-900">
                    @if($totalAllocated > 0)
                        {{ round((($totalAllocated - $totalRemaining) / $totalAllocated) * 100) }}%
                    @else
                        0%
                    @endif
                </p>
            </div>
        </div>
    @else
        <p class="mt-4 text-sand-500">Set up a budget to see how much you can spend.</p>
    @endif
</div>
