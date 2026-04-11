<div class="rounded-xl border border-sand-200 bg-white p-4">
    <h3 class="mb-3 text-sm font-semibold text-sand-900">Share this category</h3>
    <form wire:submit="share" class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <label for="share-email" class="sr-only">Email address</label>
            <input wire:model="email" type="email" id="share-email" placeholder="Email address..." class="block w-full rounded-lg border-sand-300 bg-sand-50 text-sm text-sand-900 shadow-sm placeholder:text-sand-400 focus:border-amber-500 focus:ring-amber-500">
            @error('email') <p class="mt-1 text-xs text-terracotta-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="share-relation" class="sr-only">Permission level</label>
            <select wire:model="relation" id="share-relation" class="w-full rounded-lg border-sand-300 bg-sand-50 text-sm text-sand-700 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:w-auto">
                <option value="viewer">Viewer</option>
                <option value="editor">Editor</option>
            </select>
        </div>
        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
            <span wire:loading.remove>Share</span>
            <span wire:loading>Sharing...</span>
        </button>
    </form>
</div>
