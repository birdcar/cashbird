<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('debt.index') }}" class="text-gray-500 hover:text-gray-700" wire:navigate>&larr; Back</a>
        <h1 class="text-2xl font-bold text-gray-900">Add Debt</h1>
    </div>

    <form wire:submit="save" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="e.g., Chase Sapphire">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                <select wire:model.live="type" id="type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    <option value="credit_card">Credit Card</option>
                    <option value="payday_loan">Payday Loan</option>
                    <option value="student_loan">Student Loan</option>
                    <option value="personal_loan">Personal Loan</option>
                    <option value="auto_loan">Auto Loan</option>
                    <option value="mortgage">Mortgage</option>
                    <option value="recovery_plan">Recovery Plan</option>
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="lender" class="block text-sm font-medium text-gray-700">Lender</label>
            <input wire:model="lender" type="text" id="lender" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="e.g., Chase, MoneyLion, Cleo">
            @error('lender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="current_balance" class="block text-sm font-medium text-gray-700">Current Balance ($)</label>
                <input wire:model="current_balance" type="number" step="0.01" id="current_balance" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="5000.00">
                @error('current_balance') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="original_balance" class="block text-sm font-medium text-gray-700">Original Balance ($)</label>
                <input wire:model="original_balance" type="number" step="0.01" id="original_balance" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="Optional">
                @error('original_balance') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="apr" class="block text-sm font-medium text-gray-700">APR (%)</label>
                <input wire:model="apr" type="number" step="0.001" id="apr" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="24.990">
                @error('apr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="minimum_payment" class="block text-sm font-medium text-gray-700">Minimum Payment ($)</label>
                <input wire:model="minimum_payment" type="number" step="0.01" id="minimum_payment" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="100.00">
                @error('minimum_payment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="due_day" class="block text-sm font-medium text-gray-700">Due Day (1-28)</label>
                <input wire:model="due_day" type="number" min="1" max="28" id="due_day" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm" placeholder="15">
                @error('due_day') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if($type === 'recovery_plan')
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                <p class="mb-3 text-sm font-medium text-yellow-800">Recovery Plan Terms</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="recovery_fixed_payment" class="block text-sm font-medium text-gray-700">Fixed Payment ($)</label>
                        <input wire:model="recovery_fixed_payment" type="number" step="0.01" id="recovery_fixed_payment" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        @error('recovery_fixed_payment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="recovery_duration_months" class="block text-sm font-medium text-gray-700">Duration (months)</label>
                        <input wire:model="recovery_duration_months" type="number" min="1" max="120" id="recovery_duration_months" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        @error('recovery_duration_months') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="recovery_start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input wire:model="recovery_start_date" type="date" id="recovery_start_date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        @error('recovery_start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-gray-900 px-6 py-3 text-sm font-medium text-white hover:bg-gray-800">
                Save Debt
            </button>
        </div>
    </form>
</div>
