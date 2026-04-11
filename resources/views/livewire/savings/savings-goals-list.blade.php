<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">Savings</h1>
        <a href="{{ route('savings.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600" wire:navigate>
            <x-phosphor-plus-circle class="h-4 w-4" />
            Add Goal
        </a>
    </div>

    {{-- Stage indicator --}}
    <div class="flex items-center gap-3 rounded-lg bg-sand-100 px-4 py-3">
        <x-phosphor-info class="h-5 w-5 shrink-0 text-amber-600" />
        <div class="text-sm">
            <span class="font-medium text-sand-900">
                @switch($stage->value)
                    @case('starter_emergency_fund')
                        Building starter emergency fund
                        @break
                    @case('debt_payoff')
                        Paying off debt
                        @break
                    @case('full_emergency_fund')
                        Building full emergency fund
                        @break
                    @case('named_goals')
                        Saving for your goals
                        @break
                @endswitch
            </span>
            <span class="text-sand-500">
                @switch($stage->value)
                    @case('starter_emergency_fund')
                        — Start with $1,000 set aside for unexpected expenses.
                        @break
                    @case('debt_payoff')
                        — Focus on paying off debt while maintaining your starter fund.
                        @break
                    @case('full_emergency_fund')
                        — Build 3 months of expenses as a safety net.
                        @break
                    @case('named_goals')
                        — You're debt-free with an emergency fund. Save for what matters!
                        @break
                @endswitch
            </span>
        </div>
    </div>

    @if(!$hasGoals)
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-piggy-bank class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="mb-4 text-sand-600">No savings goals yet. Let's set one up.</p>
            @if($systemGoal)
                <p class="mb-4 text-sm text-sand-500">
                    Based on your finances, we recommend starting with a
                    ${{ number_format($systemGoal->target_amount / 100, 2) }} emergency fund.
                </p>
                <button wire:click="createSystemGoal" class="rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600">
                    Create this goal
                </button>
            @else
                <a href="{{ route('savings.create') }}" class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600" wire:navigate>
                    Create a goal
                </a>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach($goalsWithProgress as $item)
                @php
                    $goal = $item['goal'];
                    $prog = $item['progress'];
                @endphp
                <div class="rounded-xl border border-sand-200 bg-white p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-sand-900">{{ $goal->name }}</h3>
                            <p class="mt-0.5 text-sm text-sand-500">
                                ${{ number_format($goal->current_balance / 100, 2) }} of ${{ number_format($goal->target_amount / 100, 2) }}
                                @if($prog['monthly_contribution'] > 0)
                                    &middot; ${{ number_format($prog['monthly_contribution'] / 100, 2) }}/mo
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($prog['on_track'] === 'on_track')
                                <span class="inline-flex items-center gap-1 rounded-full bg-sage-100 px-2.5 py-0.5 text-xs font-medium text-sage-700">
                                    <x-phosphor-check-circle class="h-3.5 w-3.5" /> On track
                                </span>
                            @elseif($prog['on_track'] === 'at_risk')
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                    <x-phosphor-clock class="h-3.5 w-3.5" /> At risk
                                </span>
                            @elseif($prog['on_track'] === 'behind')
                                <span class="inline-flex items-center gap-1 rounded-full bg-terracotta-100 px-2.5 py-0.5 text-xs font-medium text-terracotta-700">
                                    <x-phosphor-warning class="h-3.5 w-3.5" /> Behind
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs text-sand-500 mb-1">
                            <span>{{ $prog['progress'] }}%</span>
                            @if($prog['next_milestone'])
                                <span>Next: {{ $prog['next_milestone'] }}%</span>
                            @endif
                        </div>
                        <div class="h-2.5 w-full rounded-full bg-sand-100"
                             role="progressbar"
                             aria-valuenow="{{ $prog['progress'] }}"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             aria-label="{{ $goal->name }}: {{ $prog['progress'] }}% complete">
                            <div class="h-2.5 rounded-full bg-amber-500 transition-all" style="width: {{ $prog['progress'] }}%"></div>
                        </div>
                    </div>

                    @if($prog['projected_completion'])
                        <p class="mt-3 text-xs text-sand-400">
                            Projected completion: {{ $prog['projected_completion']->format('M Y') }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
