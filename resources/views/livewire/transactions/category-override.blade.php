<div class="inline">
    <button wire:click="openModal" class="text-xs text-gray-500 underline hover:text-gray-700" aria-label="Change category for this transaction">
        Change
    </button>

    @if($showModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            wire:keydown.escape="$set('showModal', false)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="category-override-title"
            x-data
            x-init="
                const focusable = $el.querySelectorAll('button, select, [tabindex]:not([tabindex=\'-1\'])');
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                first && first.focus();
                $el.addEventListener('keydown', function(e) {
                    if (e.key !== 'Tab') return;
                    if (e.shiftKey) {
                        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                    } else {
                        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
                    }
                });
            "
        >
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 id="category-override-title" class="mb-4 text-lg font-semibold text-gray-900">Override Category</h3>

                <select wire:model.live="selectedCategoryId" aria-label="Category" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Select category...</option>
                    @foreach($categories as $parent)
                        <optgroup label="{{ $parent->name }}">
                            @foreach($parent->children as $child)
                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>

                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button wire:click="save" type="button" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-800" @disabled(!$selectedCategoryId)>
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
