<div class="rounded-lg border border-gray-200 bg-white">
    <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">Payoff Timeline</h2>
    </div>

    @if($milestones->isEmpty())
        <div class="p-6 text-center text-gray-500">Add debts to see your payoff timeline.</div>
    @else
        <div class="p-6">
            <div class="relative">
                <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200"></div>
                @foreach($milestones as $milestone)
                    <div class="relative mb-6 ml-10 last:mb-0">
                        <div class="absolute -left-[1.625rem] top-1 h-3 w-3 rounded-full border-2 border-gray-800 bg-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $milestone['debt_name'] }} paid off</p>
                            <p class="text-sm text-gray-500">
                                {{ $milestone['payoff_date']->format('M Y') }}
                                (month {{ $milestone['payoff_month'] }})
                                &middot; frees ${{ number_format($milestone['freed_amount'] / 100, 2) }}/mo
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm">
                <p class="font-medium text-gray-900">
                    Debt-free in {{ $schedule->monthsToDebtFree }} months ({{ $schedule->projectedDebtFreeDate->format('M Y') }})
                </p>
                <p class="text-gray-500">
                    Total interest: ${{ number_format($schedule->totalInterestPaid / 100, 2) }}
                </p>
            </div>
        </div>
    @endif
</div>
