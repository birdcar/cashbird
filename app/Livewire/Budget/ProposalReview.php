<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetProposal;
use Illuminate\View\View;
use Livewire\Component;

class ProposalReview extends Component
{
    public string $proposalId;

    public function mount(string $proposalId): void
    {
        $this->proposalId = $proposalId;
    }

    public function approve(): void
    {
        $proposal = $this->ownedProposal();
        if (! $proposal) {
            return;
        }

        $period = $proposal->period;

        foreach ($proposal->changes as $change) {
            BudgetAllocation::where('budget_period_id', $period->id)
                ->where('category_id', $change['category_id'])
                ->update(['allocated_amount' => $change['new_amount']]);
        }

        $period->update([
            'total_allocated' => $period->allocations()->sum('allocated_amount'),
        ]);

        $proposal->update([
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $this->dispatch('proposal-reviewed');
    }

    public function reject(): void
    {
        $proposal = $this->ownedProposal();
        if (! $proposal) {
            return;
        }

        $proposal->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
        ]);

        $this->dispatch('proposal-reviewed');
    }

    public function render(): View
    {
        $proposal = BudgetProposal::with('period')->findOrFail($this->proposalId);

        return view('livewire.budget.proposal-review', [
            'proposal' => $proposal,
        ]);
    }

    private function ownedProposal(): ?BudgetProposal
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return BudgetProposal::with('period.budget')
            ->whereHas('period.budget', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->where('id', $this->proposalId)
            ->first();
    }
}
