<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('debt.index') }}" class="text-sand-400 transition-colors hover:text-sand-700" wire:navigate aria-label="Back to debt list">
            <x-phosphor-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">Add Debt</h1>
    </div>

    <form wire:submit="save" class="space-y-6 rounded-xl border border-sand-200 bg-white p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-sand-700">Name</label>
                <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="e.g., Chase Sapphire">
                @error('name') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-sand-700">Type</label>
                <select wire:model.live="type" id="type" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                    <option value="credit_card">Credit Card</option>
                    <option value="payday_loan">Payday Loan</option>
                    <option value="student_loan">Student Loan</option>
                    <option value="personal_loan">Personal Loan</option>
                    <option value="auto_loan">Auto Loan</option>
                    <option value="mortgage">Mortgage</option>
                    <option value="recovery_plan">Recovery Plan</option>
                </select>
                @error('type') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="lender" class="block text-sm font-medium text-sand-700">Lender</label>
            <input wire:model="lender" type="text" id="lender" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="e.g., Chase, MoneyLion, Cleo">
            @error('lender') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="current_balance" class="block text-sm font-medium text-sand-700">Current balance ($)</label>
                <input wire:model="current_balance" type="number" step="0.01" id="current_balance" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="5000.00">
                @error('current_balance') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
                @if($current_balance && $current_balance > 100000)
                    <p class="mt-1 text-xs text-amber-600">
                        <x-phosphor-warning class="inline h-3 w-3" />
                        That's over $100k — make sure you haven't added extra zeros.
                    </p>
                @endif
            </div>

            <div>
                <label for="original_balance" class="block text-sm font-medium text-sand-700">Original balance ($)</label>
                <input wire:model="original_balance" type="number" step="0.01" id="original_balance" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="Optional">
                @error('original_balance') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="apr" class="block text-sm font-medium text-sand-700">Interest rate (APR %) <x-help-tip text="Your annual percentage rate — find it on your statement or lender's website." /></label>
                <input wire:model="apr" type="number" step="0.001" id="apr" x-data x-ref="aprInput" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="24.990">
                @error('apr') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
                @if($apr && ($apr > 36 || $apr < 0))
                    <p class="mt-1 text-xs text-amber-600">
                        <x-phosphor-warning class="inline h-3 w-3" />
                        Most rates are between 0–36%. Double-check this is right.
                    </p>
                @endif
            </div>

            <div>
                <label for="minimum_payment" class="block text-sm font-medium text-sand-700">Minimum payment ($)</label>
                <input wire:model="minimum_payment" type="number" step="0.01" id="minimum_payment" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="100.00">
                @error('minimum_payment') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
                @if($minimum_payment && $current_balance && $minimum_payment > $current_balance)
                    <p class="mt-1 text-xs text-amber-600">
                        <x-phosphor-warning class="inline h-3 w-3" />
                        This is more than the balance — is that right?
                    </p>
                @endif
            </div>

            <div>
                <label for="due_day" class="block text-sm font-medium text-sand-700">Due day (1–28) <x-help-tip text="The day of the month your payment is due." /></label>
                <input wire:model="due_day" type="number" min="1" max="28" id="due_day" class="mt-1 block w-full rounded-lg border-sand-300 bg-sand-50 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="15">
                @error('due_day') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if($type === 'recovery_plan')
            <fieldset class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <legend class="px-2 text-sm font-medium text-amber-800">Recovery plan terms</legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="recovery_fixed_payment" class="block text-sm font-medium text-amber-900">Fixed payment ($)</label>
                        <input wire:model="recovery_fixed_payment" type="number" step="0.01" id="recovery_fixed_payment" class="mt-1 block w-full rounded-lg border-sand-300 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                        @error('recovery_fixed_payment') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="recovery_duration_months" class="block text-sm font-medium text-amber-900">Duration (months)</label>
                        <input wire:model="recovery_duration_months" type="number" min="1" max="120" id="recovery_duration_months" class="mt-1 block w-full rounded-lg border-sand-300 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                        @error('recovery_duration_months') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="recovery_start_date" class="block text-sm font-medium text-amber-900">Start date</label>
                        <input wire:model="recovery_start_date" type="date" id="recovery_start_date" class="mt-1 block w-full rounded-lg border-sand-300 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                        @error('recovery_start_date') <p class="mt-1 text-sm text-terracotta-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>
        @endif

        <div class="flex justify-stretch sm:justify-end">
            <button type="submit" class="w-full rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 sm:w-auto">
                Save Debt
            </button>
        </div>
    </form>
</div>
