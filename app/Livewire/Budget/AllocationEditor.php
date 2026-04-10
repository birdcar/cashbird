<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Models\BudgetAllocation;
use Illuminate\View\View;
use Livewire\Component;

class AllocationEditor extends Component
{
    public string $allocationId;

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
        $allocation = BudgetAllocation::findOrFail($this->allocationId);
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
}
