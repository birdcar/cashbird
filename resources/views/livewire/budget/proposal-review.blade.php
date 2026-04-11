<div class="mt-3 rounded-xl border border-sand-200 bg-white p-4">
    <h3 class="mb-3 text-sm font-semibold text-sand-900">Suggested budget changes</h3>

    <div class="space-y-2">
        @foreach($proposal->changes as $change)
            <div class="flex items-center justify-between text-sm">
                <span class="text-sand-700">{{ $change['category_name'] ?? 'Unknown' }}</span>
                <div class="flex items-center gap-2">
                    <span class="text-sand-400">${{ number_format($change['old_amount'] / 100, 2) }}</span>
                    <x-phosphor-arrow-right class="h-3 w-3 text-sand-400" />
                    <span class="font-medium {{ $change['new_amount'] > $change['old_amount'] ? 'text-terracotta-600' : 'text-sage-600' }}">
                        ${{ number_format($change['new_amount'] / 100, 2) }}
                    </span>
                </div>
            </div>
            <p class="text-xs text-sand-500">{{ $change['rationale'] }}</p>
        @endforeach
    </div>

    <div class="mt-4 flex gap-2">
        <button wire:click="approve" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-600" aria-label="Approve budget adjustment">
            Approve
        </button>
        <button wire:click="reject" class="rounded-lg border border-sand-300 px-4 py-2 text-sm text-sand-600 transition-colors hover:bg-sand-50" aria-label="Reject budget adjustment">
            Reject
        </button>
    </div>
</div>
