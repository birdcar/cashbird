<div class="rounded-lg border border-gray-200 bg-white p-6">
    <h2 class="mb-4 text-lg font-semibold text-gray-900">Ready to Spend</h2>

    @if($hasData)
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-sm text-gray-500">Remaining this month</p>
                <p class="text-xl font-bold {{ $totalRemaining >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                    ${{ number_format($totalRemaining / 100, 2) }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Daily safe to spend</p>
                <p class="text-xl font-bold text-gray-900">${{ number_format($totalDailySafe / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Budget utilization</p>
                <p class="text-xl font-bold text-gray-900">
                    @if($totalAllocated > 0)
                        {{ round((($totalAllocated - $totalRemaining) / $totalAllocated) * 100) }}%
                    @else
                        0%
                    @endif
                </p>
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500">Set up a budget to see your ready-to-spend amounts.</p>
    @endif
</div>
