<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Models\BudgetAllocation;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AllocationEditor extends Component
{
    public string $allocationId;

    #[Validate('required|integer|min:0')]
    public int $amount = 0;

    public bool $isLocked = false;

    public function mount(string $allocationId): void
    {
        $this->allocationId = $allocationId;
        $allocation = BudgetAllocation::findOrFail($allocationId);
        $this->amount = $allocation->allocated_amount;
        $this->isLocked = $allocation->is_locked;
    }

    public function save(): void
    {
        $this->validate();

        $allocation = $this->ownedAllocation();
        if (! $allocation) {
            return;
        }

        $allocation->update([
            'allocated_amount' => $this->amount,
            'is_locked' => $this->isLocked,
            'lock_reason' => $this->isLocked ? 'user-locked' : null,
        ]);

        $period = $allocation->period;
        $period->update([
            'total_allocated' => $period->allocations()->sum('allocated_amount'),
        ]);

        $this->dispatch('allocation-updated');
    }

    public function toggleLock(): void
    {
        $this->isLocked = ! $this->isLocked;
    }

    public function render(): View
    {
        return view('livewire.budget.allocation-editor');
    }

    private function ownedAllocation(): ?BudgetAllocation
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return BudgetAllocation::with('period.budget')
            ->whereHas('period.budget', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->where('id', $this->allocationId)
            ->first();
    }
}
