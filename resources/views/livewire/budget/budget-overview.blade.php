<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Budget</h1>
    </div>

    @if(!$hasBudget)
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="mb-4 text-gray-600">No budget set up yet. Create one based on your spending history.</p>
            <button wire:click="createBudget" wire:loading.attr="disabled" class="rounded-lg bg-gray-900 px-6 py-3 text-sm font-medium text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove>Generate Budget</span>
                <span wire:loading>Generating…</span>
            </button>
        </div>
    @elseif($period)
        @if($proposals->isNotEmpty())
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                <p class="text-sm font-medium text-yellow-800">
                    You have {{ $proposals->count() }} pending budget proposal(s) to review.
                </p>
                @foreach($proposals as $proposal)
                    <livewire:budget.proposal-review :proposal-id="$proposal->id" :key="'prop-'.$proposal->id" />
                @endforeach
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-600">Monthly Income</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($period->total_income / 100, 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-600">Total Allocated</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($period->total_allocated / 100, 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-600">Unallocated</p>
                <p class="text-2xl font-bold {{ ($period->total_income - $period->total_allocated) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    ${{ number_format(($period->total_income - $period->total_allocated) / 100, 2) }}
                </p>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Allocations — {{ $period->month->format('F Y') }}</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($allocations as $allocation)
                    @php $rts = $rtsData[$allocation->category_id] ?? null; @endphp
                    <div wire:key="{{ $allocation->id }}" class="flex items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-gray-900">{{ $allocation->category?->name ?? 'Unknown' }}</span>
                            @if($allocation->is_locked)
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Locked</span>
                            @endif
                            @if($allocation->is_fixed)
                                <span class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-600">Fixed</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="text-right">
                                <p class="font-medium text-gray-900">${{ number_format($allocation->allocated_amount / 100, 2) }}</p>
                                @if($rts)
                                    <p class="text-xs {{ $rts['remaining'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        ${{ number_format($rts['remaining'] / 100, 2) }} left
                                    </p>
                                @endif
                            </div>
                            @if($rts && $allocation->allocated_amount > 0)
                                <div class="w-24">
                                    @php $pct = min(100, ($rts['spent'] + $rts['pending']) / $allocation->allocated_amount * 100) @endphp
                                    <div class="h-2 w-full rounded-full bg-gray-100"
                                         role="progressbar"
                                         aria-valuenow="{{ (int) round($pct) }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         aria-label="{{ $allocation->category?->name ?? 'Category' }} spending: {{ (int) round($pct) }}%">
                                        <div class="h-2 rounded-full {{ $pct > 100 ? 'bg-red-500' : 'bg-gray-800' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="text-gray-600">No active budget period. Generate a new budget to get started.</p>
        </div>
    @endif
</div>
