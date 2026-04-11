<div class="rounded-lg border border-gray-200 bg-white p-4">
    <h3 class="mb-3 text-sm font-semibold text-gray-900">Share this category</h3>
    <form wire:submit="share" class="flex gap-3">
        <div class="flex-1">
            <input wire:model="email" type="email" placeholder="User email..." class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <select wire:model="relation" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            <option value="viewer">Viewer</option>
            <option value="editor">Editor</option>
        </select>
        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
            Share
        </button>
    </form>
</div>
