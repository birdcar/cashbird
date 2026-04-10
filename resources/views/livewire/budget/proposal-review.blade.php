<div class="mt-3 rounded-lg border border-gray-200 bg-white p-4">
    <h3 class="mb-3 text-sm font-semibold text-gray-900">Budget Adjustment Proposal</h3>

    <div class="space-y-2">
        @foreach($proposal->changes as $change)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-700">{{ $change['category_name'] ?? 'Unknown' }}</span>
                <div class="flex items-center gap-2">
                    <span class="text-gray-400">${{ number_format($change['old_amount'] / 100, 2) }}</span>
                    <span class="text-gray-400">&rarr;</span>
                    <span class="font-medium {{ $change['new_amount'] > $change['old_amount'] ? 'text-red-600' : 'text-green-600' }}">
                        ${{ number_format($change['new_amount'] / 100, 2) }}
                    </span>
                </div>
            </div>
            <p class="text-xs text-gray-500">{{ $change['rationale'] }}</p>
        @endforeach
    </div>

    <div class="mt-4 flex gap-2">
        <button wire:click="approve" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-800">
            Approve
        </button>
        <button wire:click="reject" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            Reject
        </button>
    </div>
</div>
