<div class="flex items-center gap-3">
    <input
        wire:model.live="amount"
        type="number"
        step="100"
        min="0"
        aria-label="Budget amount in cents"
        class="w-28 rounded-lg border border-sand-300 bg-sand-50 px-3 py-1 text-sm text-sand-900 focus:border-amber-500 focus:ring-amber-500"
    />
    <button
        wire:click="toggleLock"
        type="button"
        class="inline-flex items-center gap-1 text-sm transition-colors {{ $isLocked ? 'text-amber-700 font-medium' : 'text-sand-400 hover:text-sand-600' }}"
        aria-label="{{ $isLocked ? 'Unlock category budget' : 'Lock category budget' }}"
    >
        @if($isLocked)
            <x-phosphor-lock-simple-fill class="h-3.5 w-3.5" />
        @else
            <x-phosphor-lock-simple-open class="h-3.5 w-3.5" />
        @endif
        {{ $isLocked ? 'Locked' : 'Lock' }}
    </button>
    <button
        wire:click="save"
        type="button"
        class="rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white transition-colors hover:bg-amber-600"
        aria-label="Save category budget"
    >
        Save
    </button>
</div>
