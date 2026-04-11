<div class="space-y-8">
    <div>
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">Net Worth</h1>
    </div>

    @if(!$hasData)
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-chart-line class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="mb-4 text-sand-600">Connect a bank account to start tracking your net worth.</p>
            <a href="{{ route('accounts.connect') }}" class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600" wire:navigate>
                Connect account
            </a>
        </div>
    @else
        {{-- Stats floating free --}}
        <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Total assets</p>
                <p class="mt-1 font-display text-2xl font-semibold text-sand-900">${{ number_format($totalAssets / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Total debts</p>
                <p class="mt-1 font-display text-2xl font-semibold text-terracotta-600">${{ number_format($totalDebts / 100, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-sand-400">Net worth</p>
                <p class="mt-1 font-display text-2xl font-semibold {{ $netWorth >= 0 ? 'text-sand-900' : 'text-terracotta-600' }}">
                    ${{ number_format(abs($netWorth) / 100, 2) }}
                </p>
                @if($change !== null)
                    <p class="mt-0.5 text-sm {{ $change >= 0 ? 'text-sage-600' : 'text-terracotta-600' }}">
                        @if($change >= 0)
                            <x-phosphor-arrow-up class="inline h-3.5 w-3.5" />
                        @else
                            <x-phosphor-arrow-down class="inline h-3.5 w-3.5" />
                        @endif
                        ${{ number_format(abs($change) / 100, 2) }} from last month
                    </p>
                @endif
            </div>
        </div>

        {{-- Trend chart --}}
        @if($trend->isNotEmpty())
            <div class="rounded-xl border border-sand-200 bg-white p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-sand-900">Net worth over time</h2>

                @php
                    $maxVal = $trend->max('net_worth') ?: 1;
                    $minVal = $trend->min('net_worth');
                    $range = max($maxVal - min(0, $minVal), 1);
                @endphp
                <div role="img" aria-label="Net worth trend: {{ $trend->map(fn($s) => \Carbon\Carbon::parse($s->month)->format('M Y') . ' $' . number_format($s->net_worth / 100, 2))->join(', ') }}">
                    <div class="flex items-end gap-2" style="height: 120px;" aria-hidden="true">
                        @foreach($trend as $snapshot)
                            @php $height = $range > 0 ? max(4, (int) round((($snapshot->net_worth - min(0, $minVal)) / $range) * 100)) : 4 @endphp
                            <div wire:key="snapshot-{{ $snapshot->id }}" class="flex flex-1 flex-col items-center gap-1">
                                <div class="w-full rounded-t {{ $snapshot->net_worth >= 0 ? 'bg-sage-400' : 'bg-terracotta-400' }}"
                                     style="height: {{ $height }}px">
                                </div>
                                <span class="text-xs text-sand-500">{{ \Carbon\Carbon::parse($snapshot->month)->format('M') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Breakdown --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Accounts --}}
            <div class="rounded-xl border border-sand-200 bg-white">
                <div class="border-b border-sand-100 px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-sand-900">Accounts</h2>
                </div>
                @if(empty($accounts))
                    <div class="px-6 py-4 text-sm text-sand-400">No accounts connected.</div>
                @else
                    <div class="divide-y divide-sand-100">
                        @foreach($accounts as $account)
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="font-medium text-sand-900">{{ $account['name'] }}</p>
                                    <p class="text-sm text-sand-500">{{ ucfirst(str_replace('_', ' ', $account['type'])) }}</p>
                                </div>
                                <p class="font-medium {{ $account['balance'] >= 0 ? 'text-sand-900' : 'text-terracotta-600' }}">
                                    ${{ number_format(abs($account['balance']) / 100, 2) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Debts --}}
            <div class="rounded-xl border border-sand-200 bg-white">
                <div class="border-b border-sand-100 px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-sand-900">Manual debts</h2>
                </div>
                @if(empty($debts))
                    <div class="px-6 py-4 text-sm text-sand-400">No manual debts tracked.</div>
                @else
                    <div class="divide-y divide-sand-100">
                        @foreach($debts as $debt)
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="font-medium text-sand-900">{{ $debt['name'] }}</p>
                                    <p class="text-sm text-sand-500">{{ ucfirst(str_replace('_', ' ', $debt['type'])) }}</p>
                                </div>
                                <p class="font-medium text-terracotta-600">${{ number_format($debt['balance'] / 100, 2) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
