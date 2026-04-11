<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Models\BudgetAllocation;
use App\Models\SharingInvitation;
use App\Services\WorkOS\FGAService;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AllocationEditor extends Component
{
    #[Locked]
    public string $allocationId;

    #[Validate('required|integer|min:0')]
    public int $amount = 0;

    public bool $isLocked = false;

    public bool $isReadOnly = false;

    public function mount(string $allocationId): void
    {
        $this->allocationId = $allocationId;
        $allocation = BudgetAllocation::findOrFail($allocationId);
        $this->amount = $allocation->allocated_amount;
        $this->isLocked = $allocation->is_locked;
        $this->isReadOnly = ! $this->canEdit($allocation);
    }

    public function save(): void
    {
        if ($this->isReadOnly) {
            return;
        }

        $this->validate();

        $allocation = $this->ownedOrEditableAllocation();
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

    private function ownedOrEditableAllocation(): ?BudgetAllocation
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $allocation = BudgetAllocation::with('period.budget')
            ->where('id', $this->allocationId)
            ->first();

        if (! $allocation) {
            return null;
        }

        $isOwner = $allocation->period?->budget?->user_id === $user->getAuthIdentifier();

        if ($isOwner) {
            return $allocation;
        }

        if ($user->workos_id && app(FGAService::class)->check('budget_category', $allocation->category_id, 'editor', 'user', $user->workos_id)) {
            return $allocation;
        }

        return null;
    }

    private function canEdit(BudgetAllocation $allocation): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $isOwner = $allocation->period?->budget?->user_id === $user->getAuthIdentifier();
        if ($isOwner) {
            return true;
        }

        if (! $user->workos_id) {
            return false;
        }

        $hasSharedAccess = SharingInvitation::where('to_user_id', $user->id)
            ->where('resource_type', 'budget_category')
            ->where('resource_id', $allocation->category_id)
            ->active()
            ->exists();

        if (! $hasSharedAccess) {
            return false;
        }

        return app(FGAService::class)->check('budget_category', $allocation->category_id, 'editor', 'user', $user->workos_id);
    }
}
