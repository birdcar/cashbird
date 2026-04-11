<div class="inline">
    @if(!$showModal)
        <button wire:click="openModal" class="text-xs text-amber-600 hover:text-amber-700" aria-label="Change category for this transaction">
            Change
        </button>
    @else
        <span class="inline-flex items-center gap-1.5" x-show="true" x-transition.opacity.duration.150ms>
            <select wire:model.live="selectedCategoryId" aria-label="Category" class="rounded border-sand-300 bg-sand-50 px-2 py-0.5 text-xs text-sand-900 focus:border-amber-500 focus:ring-amber-500">
                <option value="">Pick...</option>
                @foreach($categories as $parent)
                    <optgroup label="{{ $parent->name }}">
                        @foreach($parent->children as $child)
                            <option value="{{ $child->id }}">{{ $child->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <button wire:click="save" type="button" class="rounded bg-amber-500 px-2 py-0.5 text-xs font-medium text-white transition-colors hover:bg-amber-600" @disabled(!$selectedCategoryId) aria-label="Save category">
                <x-phosphor-check class="h-3 w-3" />
            </button>
            <button wire:click="$set('showModal', false)" type="button" class="text-xs text-sand-400 transition-colors hover:text-sand-600" aria-label="Cancel">
                <x-phosphor-x class="h-3 w-3" />
            </button>
        </span>
    @endif
</div>
