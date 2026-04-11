<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">Budget</h1>
    </div>

    @if(!$hasBudget)
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-chart-pie-slice class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="mb-4 text-sand-600">No budget yet — let Cashbird build one from your spending.</p>
            <button wire:click="createBudget" wire:confirm="This will build a budget from your spending history. Ready?" wire:loading.attr="disabled" class="rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove>Create my budget</span>
                <span wire:loading>Building your budget…</span>
            </button>
        </div>
    @elseif($period)
        @if($proposals->isNotEmpty())
            <div x-data x-intersect.once="$el.classList.add('animate-in')" class="rounded-xl border border-amber-200 bg-amber-50 p-4 opacity-0 translate-y-2 transition-all duration-300 ease-out">
                <p class="text-sm font-medium text-amber-800">
                    You have {{ $proposals->count() }} pending budget suggestion(s) to review.
                </p>
                @foreach($proposals as $proposal)
                    <livewire:budget.proposal-review :proposal-id="$proposal->id" wire:key="prop-{{ $proposal->id }}" />
                @endforeach
            </div>
        @endif

        {{-- Stats floating free --}}
        <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Monthly income</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($period->total_income / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-amber-500">Needs</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($needsTotal / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sage-500">Wants</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($wantsTotal / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-terracotta-500">Savings</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($savingsTotal / 100, 2) }}</p>
            </div>
        </div>

        {{-- 50/30/20 visual bar --}}
        <div>
            <div class="flex h-3 overflow-hidden rounded-full">
                <div class="bg-amber-400" style="width: {{ $needsPercent }}%"></div>
                <div class="bg-sand-300" style="width: {{ $wantsPercent }}%"></div>
                <div class="bg-sage-400" style="width: {{ $savingsPercent }}%"></div>
            </div>
            <div class="mt-1.5 flex items-center justify-between text-xs text-sand-400">
                <span>Needs {{ $needsPercent }}% <span class="text-sand-300">(target 50%)</span></span>
                <span>Wants {{ $wantsPercent }}% <span class="text-sand-300">(30%)</span></span>
                <span>Savings {{ $savingsPercent }}% <span class="text-sand-300">(20%)</span></span>
            </div>
        </div>

        <div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Unbudgeted <x-help-tip text="Income that hasn't been assigned to a category yet. Ideally this is close to zero." /></p>
                <p class="mt-1 font-display text-2xl font-semibold {{ ($period->total_income - $period->total_allocated) >= 0 ? 'text-sage-600' : 'text-terracotta-600' }}">
                    ${{ number_format(($period->total_income - $period->total_allocated) / 100, 2) }}
                </p>
            </div>
        </div>

        {{-- Categories list — keep card, it's interactive --}}
        <div class="rounded-xl border border-sand-200 bg-white">
            <div class="border-b border-sand-100 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-sand-900">Your categories — {{ $period->month->format('F Y') }}</h2>
            </div>
            <div class="divide-y divide-sand-100">
                @foreach($allocations as $allocation)
                    @php $rts = $rtsData[$allocation->category_id] ?? null; @endphp
                    <div wire:key="{{ $allocation->id }}" class="flex items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-sand-900">{{ $allocation->category?->name ?? 'Unknown' }}</span>
                            @if($allocation->is_locked)
                                <span class="inline-flex items-center gap-1 rounded bg-sand-100 px-2 py-0.5 text-xs text-sand-600">
                                    <x-phosphor-lock-simple-fill class="h-3 w-3" /> Locked
                                </span>
                            @endif
                            @if($allocation->is_fixed)
                                <span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Fixed</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="text-right">
                                <p class="font-medium text-sand-900">${{ number_format($allocation->allocated_amount / 100, 2) }}</p>
                                @if($rts)
                                    <p class="text-xs {{ $rts['remaining'] >= 0 ? 'text-sage-600' : 'text-terracotta-600' }}">
                                        ${{ number_format($rts['remaining'] / 100, 2) }} left
                                    </p>
                                @endif
                            </div>
                            @if($rts && $allocation->allocated_amount > 0)
                                <div class="w-24">
                                    @php $pct = min(100, ($rts['spent'] + $rts['pending']) / $allocation->allocated_amount * 100) @endphp
                                    <div class="h-2 w-full rounded-full bg-sand-100"
                                         role="progressbar"
                                         aria-valuenow="{{ (int) round($pct) }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         aria-label="{{ $allocation->category?->name ?? 'Category' }} spending: {{ (int) round($pct) }}%">
                                        <div class="h-2 rounded-full {{ $pct > 100 ? 'bg-terracotta-500' : 'bg-amber-500' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @if($sharedAllocations->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50">
                <div class="border-b border-amber-200 px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-sand-900">Shared with you</h2>
                </div>
                <div class="divide-y divide-amber-100">
                    @foreach($sharedAllocations as $shared)
                        <div wire:key="shared-{{ $shared['allocation']->id }}" class="flex items-center justify-between px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="font-medium text-sand-900">{{ $shared['allocation']->category?->name ?? 'Unknown' }}</span>
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-700">{{ ucfirst($shared['relation']) }}</span>
                                <span class="text-xs text-sand-500">from {{ $shared['shared_by'] }}</span>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-medium text-sand-900">${{ number_format($shared['allocation']->allocated_amount / 100, 2) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-chart-pie-slice class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="text-sand-600">No active budget. Create one to start tracking your spending.</p>
        </div>
    @endif
</div>
