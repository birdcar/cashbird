<div class="rounded-xl border border-sand-200 bg-white">
    <div class="flex items-center justify-between border-b border-sand-100 px-6 py-4">
        <h2 class="font-display text-lg font-semibold text-sand-900">Payoff Timeline</h2>
        <div class="flex items-center gap-2">
            <label for="monthly-extra" class="text-sm text-sand-500">Extra/mo $</label>
            <input wire:model.live.debounce.500ms="monthlyExtra" type="number" id="monthly-extra" min="0" step="100"
                   class="w-24 rounded-lg border-sand-300 bg-sand-50 px-2 py-1 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
        </div>
    </div>

    @if($milestones->isEmpty())
        <div class="p-8 text-center">
            <x-phosphor-flag-pennant class="mx-auto mb-2 h-8 w-8 text-sand-300" />
            <p class="text-sand-500">Add debts to see your payoff timeline.</p>
        </div>
    @else
        <div class="p-6">
            <div class="relative">
                <div class="absolute left-4 top-0 h-full w-0.5 bg-sand-200"></div>
                @foreach($milestones as $milestone)
                    <div wire:key="milestone-{{ $milestone['payoff_month'] }}-{{ Str::slug($milestone['debt_name']) }}" class="relative mb-6 ml-10 last:mb-0">
                        <div class="absolute -left-[1.625rem] top-1 h-3 w-3 rounded-full border-2 border-amber-500 bg-white"></div>
                        <div>
                            <p class="font-medium text-sand-900">{{ $milestone['debt_name'] }} paid off</p>
                            <p class="text-sm text-sand-500">
                                {{ $milestone['payoff_date']->format('M Y') }}
                                (month {{ $milestone['payoff_month'] }})
                                &middot; frees ${{ number_format($milestone['freed_amount'] / 100, 2) }}/mo
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 rounded-lg bg-sage-50 p-4 text-sm">
                <p class="font-medium text-sage-900">
                    Debt-free in {{ $schedule->monthsToDebtFree }} months ({{ $schedule->projectedDebtFreeDate->format('M Y') }})
                </p>
                <p class="text-sage-700">
                    Total interest: ${{ number_format($schedule->totalInterestPaid / 100, 2) }}
                </p>
            </div>
        </div>
    @endif
</div>
