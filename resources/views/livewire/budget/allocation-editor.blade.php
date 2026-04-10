<div class="flex items-center gap-3">
    <input
        wire:model.live="amount"
        type="number"
        step="100"
        min="0"
        aria-label="Allocation amount in cents"
        class="w-28 rounded-lg border border-gray-300 px-3 py-1 text-sm"
    />
    <button
        wire:click="toggleLock"
        type="button"
        class="text-sm {{ $isLocked ? 'text-gray-900 font-medium' : 'text-gray-400' }}"
        aria-label="{{ $isLocked ? 'Unlock allocation' : 'Lock allocation' }}"
    >
        {{ $isLocked ? 'Locked' : 'Lock' }}
    </button>
    <button
        wire:click="save"
        type="button"
        class="rounded bg-gray-900 px-3 py-1 text-xs text-white hover:bg-gray-800"
    >
        Save
    </button>
</div>
