<div class="space-y-8">
    <h1 class="font-display text-fluid-lg font-bold text-sand-900">Transactions</h1>

    <div class="flex flex-wrap items-center gap-3">
        <div class="relative">
            <x-phosphor-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-sand-400" />
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search transactions..."
                aria-label="Search transactions"
                class="rounded-lg border border-sand-300 bg-white py-2 pl-9 pr-4 text-sm text-sand-900 placeholder:text-sand-400 focus:border-amber-500 focus:ring-amber-500"
            />
        </div>
        <input wire:model.live="dateFrom" type="date" aria-label="From date" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm text-sand-700 focus:border-amber-500 focus:ring-amber-500" />
        <input wire:model.live="dateTo" type="date" aria-label="To date" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm text-sand-700 focus:border-amber-500 focus:ring-amber-500" />
        <select wire:model.live="categoryFilter" aria-label="Filter by category" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm text-sand-700 focus:border-amber-500 focus:ring-amber-500">
            <option value="">All Categories</option>
            @foreach($categories as $parent)
                <optgroup label="{{ $parent->name }}">
                    @foreach($parent->children as $child)
                        <option value="{{ $child->id }}">{{ $child->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-sand-200 bg-white">
        <table class="min-w-full divide-y divide-sand-200">
            <thead class="bg-sand-50">
                <tr>
                    <th wire:click="sort('date')" wire:keydown.enter="sort('date')" role="button" tabindex="0" aria-sort="{{ $sortBy === 'date' ? ($sortDir === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-sand-500">
                        Date
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-sand-500">
                        Description
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-sand-500">
                        Account
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-sand-500">
                        Category
                    </th>
                    <th wire:click="sort('amount')" wire:keydown.enter="sort('amount')" role="button" tabindex="0" aria-sort="{{ $sortBy === 'amount' ? ($sortDir === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-sand-500">
                        Amount
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @forelse($transactions as $transaction)
                    <tr class="transition-colors hover:bg-sand-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-sand-500">
                            {{ $transaction->date->format('M j, Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-sand-900">
                            {{ $transaction->description }}
                            @if($transaction->merchant_name)
                                <span class="block text-xs text-sand-400">{{ $transaction->merchant_name }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-sand-500">
                            {{ $transaction->account->name }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-sand-500">
                            <span>{{ $transaction->category?->name ?? '—' }}</span>
                            <livewire:transactions.category-override :transaction-id="$transaction->id" :key="'cat-'.$transaction->id" />
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium {{ $transaction->amount < 0 ? 'text-terracotta-600' : 'text-sage-600' }}">
                            ${{ number_format(abs($transaction->amount) / 100, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-sand-500">
                            <x-phosphor-receipt class="mx-auto mb-2 h-8 w-8 text-sand-300" />
                            No transactions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
