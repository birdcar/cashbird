<div class="space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-fluid-lg font-bold text-sand-900">Accounts</h1>
            <a href="{{ route('accounts.connect') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600">
                <x-phosphor-plus-circle class="h-4 w-4" />
                Connect Account
            </a>
        </div>

        @if($accounts->isEmpty())
            <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
                <x-phosphor-bank class="mx-auto mb-3 h-10 w-10 text-sand-300" />
                <p class="text-sand-600">No accounts connected yet.</p>
                <a href="{{ route('accounts.connect') }}" class="mt-2 inline-block text-sm font-medium text-amber-600 hover:text-amber-700">
                    Connect your first bank account
                </a>
            </div>
        @else
            @php $grouped = $accounts->groupBy('institution_id') @endphp
            @foreach($grouped as $institutionId => $institutionAccounts)
                <div class="rounded-xl border border-sand-200 bg-white">
                    <div class="border-b border-sand-100 px-6 py-4">
                        <h2 class="font-display text-lg font-semibold text-sand-900">
                            {{ $institutionAccounts->first()->institution->name }}
                        </h2>
                    </div>
                    <div class="divide-y divide-sand-100">
                        @foreach($institutionAccounts as $account)
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="font-medium text-sand-900">{{ $account->name }}</p>
                                    <p class="text-sm text-sand-500">{{ ucfirst(str_replace('_', ' ', $account->type)) }}</p>
                                </div>
                                <div class="text-right">
                                    @if($account->balance_current !== null)
                                        <p class="font-medium text-sand-900">
                                            ${{ number_format($account->balance_current / 100, 2) }}
                                        </p>
                                    @endif
                                    @if($account->last_synced_at)
                                        <p class="text-xs text-sand-400">
                                            Synced {{ $account->last_synced_at->diffForHumans() }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
</div>
