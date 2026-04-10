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
        $proposal = BudgetProposal::findOrFail($this->proposalId);
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
        $proposal = BudgetProposal::findOrFail($this->proposalId);

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
}
