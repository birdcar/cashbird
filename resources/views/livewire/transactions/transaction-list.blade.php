<x-layouts.app title="Transactions">
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-gray-900">Transactions</h1>

        <div class="flex flex-wrap items-center gap-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search transactions..."
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm"
            />
            <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" />
            <input wire:model.live="dateTo" type="date" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" />
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th wire:click="sort('date')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Account
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Category
                        </th>
                        <th wire:click="sort('amount')" class="cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($transactions as $transaction)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ $transaction->date->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ $transaction->description }}
                                @if($transaction->merchant_name)
                                    <span class="block text-xs text-gray-400">{{ $transaction->merchant_name }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ $transaction->account->name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ $transaction->category?->name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium {{ $transaction->amount < 0 ? 'text-red-600' : 'text-green-600' }}">
                                ${{ number_format(abs($transaction->amount) / 100, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                No transactions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $transactions->links() }}</div>
    </div>
</x-layouts.app>
