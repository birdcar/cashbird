<div class="rounded-xl border border-sand-200 bg-white p-8">
    <div class="flex items-center justify-between">
        <p class="mb-1 text-sm font-medium uppercase tracking-wide text-sand-400">Net Worth</p>
        <a href="{{ route('net-worth.index') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700" wire:navigate>
            View details
        </a>
    </div>

    @if($hasData)
        <div class="mt-2 mb-6">
            <p class="font-display text-fluid-xl font-semibold {{ $netWorth >= 0 ? 'text-sand-900' : 'text-terracotta-600' }}"
               aria-label="{{ $netWorth < 0 ? 'Negative ' : '' }}${{ number_format(abs($netWorth) / 100, 2) }}">
                ${{ number_format(abs($netWorth) / 100, 2) }}@if($netWorth < 0)<span class="text-lg"> (negative)</span>@endif
            </p>
            @if($change !== null)
                <p class="mt-1 text-sm {{ $change >= 0 ? 'text-sage-600' : 'text-terracotta-600' }}">
                    @if($change >= 0)
                        <x-phosphor-arrow-up class="inline h-4 w-4" aria-hidden="true" />
                    @else
                        <x-phosphor-arrow-down class="inline h-4 w-4" aria-hidden="true" />
                    @endif
                    ${{ number_format(abs($change) / 100, 2) }} from last month
                </p>
            @endif
        </div>

        <div class="grid gap-6 sm:grid-cols-2 border-t border-sand-200 pt-6">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Total assets</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($totalAssets / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Total debts</p>
                <p class="mt-1 font-display text-2xl font-semibold text-terracotta-600">${{ number_format($totalDebts / 100, 2) }}</p>
            </div>
        </div>
    @else
        <p class="mt-4 text-sand-500">Connect a bank account to see your net worth.</p>
    @endif
</div>
