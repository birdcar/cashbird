<div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Accounts</h1>
            <a href="{{ route('accounts.connect') }}"
               class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                Connect Account
            </a>
        </div>

        @if($accounts->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
                <p class="text-gray-600">No accounts connected yet.</p>
                <a href="{{ route('accounts.connect') }}" class="mt-2 inline-block text-sm font-medium text-gray-900 underline">
                    Connect your first bank account
                </a>
            </div>
        @else
            @php $grouped = $accounts->groupBy('institution_id') @endphp
            @foreach($grouped as $institutionId => $institutionAccounts)
                <div class="rounded-lg border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $institutionAccounts->first()->institution->name }}
                        </h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($institutionAccounts as $account)
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $account->name }}</p>
                                    <p class="text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $account->type)) }}</p>
                                </div>
                                <div class="text-right">
                                    @if($account->balance_current !== null)
                                        <p class="font-medium text-gray-900">
                                            ${{ number_format($account->balance_current / 100, 2) }}
                                        </p>
                                    @endif
                                    @if($account->last_synced_at)
                                        <p class="text-xs text-gray-400">
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
