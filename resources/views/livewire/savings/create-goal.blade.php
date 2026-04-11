<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('savings.index') }}" class="text-sand-400 transition-colors hover:text-sand-700" wire:navigate aria-label="Back to savings goals">
            <x-phosphor-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">Add Goal</h1>
    </div>

    <form wire:submit="save" class="space-y-6 rounded-xl border border-sand-200 bg-white p-6">
        <div>
            <label for="name" class="block text-sm font-medium text-sand-700">Goal name</label>
            <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="e.g., Emergency Fund, Vacation, New Car">
            @error('name') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="target_amount" class="block text-sm font-medium text-sand-700">Target amount ($)</label>
                <input wire:model="target_amount" type="number" step="0.01" id="target_amount" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="1000.00">
                @error('target_amount') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="monthly_contribution" class="block text-sm font-medium text-sand-700">Monthly contribution ($)</label>
                <input wire:model="monthly_contribution" type="number" step="0.01" id="monthly_contribution" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="Optional">
                @error('monthly_contribution') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="target_date" class="block text-sm font-medium text-sand-700">Target date (optional)</label>
            <input wire:model="target_date" type="date" id="target_date" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
            @error('target_date') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex sm:justify-end">
            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 disabled:opacity-50 sm:w-auto">
                <span wire:loading.remove>Save Goal</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </form>
</div>
