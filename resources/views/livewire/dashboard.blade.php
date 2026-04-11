<x-layouts.app title="Dashboard">
    @php
        $now = \Carbon\Carbon::now();
        $hour = $now->hour;
        $greeting = match(true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
        $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
    @endphp

    <div class="space-y-8">

        {{-- Header --}}
        <div x-data x-intersect.once="$el.classList.add('animate-in')" class="opacity-0 translate-y-4 transition-all duration-500 ease-out" style="transition-delay: 0ms">
            <h1 class="font-display text-fluid-lg font-semibold text-sand-900">
                {{ $greeting }}, {{ $firstName }}.
            </h1>
            <p class="mt-1 text-sm text-sand-500">{{ $now->format('l, F j, Y') }}</p>
        </div>

        {{-- Hero: Ready to Spend --}}
        <div x-data x-intersect.once="$el.classList.add('animate-in')" class="opacity-0 translate-y-4 transition-all duration-500 ease-out" style="transition-delay: 100ms">
            <livewire:budget.ready-to-spend-card />
        </div>

        {{-- Mid-section: Spending Breakdown + Insights side by side --}}
        <div x-data x-intersect.once="$el.classList.add('animate-in')" class="opacity-0 translate-y-4 transition-all duration-500 ease-out" style="transition-delay: 250ms">
            <div class="grid gap-6 lg:grid-cols-2">
                <livewire:dashboard.spending-breakdown />
                <livewire:dashboard.insights-summary />
            </div>
        </div>

        {{-- Footer: Payoff Timeline --}}
        <div x-data x-intersect.once="$el.classList.add('animate-in')" class="opacity-0 translate-y-4 transition-all duration-500 ease-out" style="transition-delay: 400ms">
            <livewire:debt.payoff-timeline />
        </div>

        {{-- Net Worth --}}
        <div x-data x-intersect.once="$el.classList.add('animate-in')" class="opacity-0 translate-y-4 transition-all duration-500 ease-out" style="transition-delay: 550ms">
            <livewire:dashboard.net-worth-card />
        </div>

    </div>
</x-layouts.app>
