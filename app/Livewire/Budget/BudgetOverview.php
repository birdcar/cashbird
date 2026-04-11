<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Models\BudgetAllocation;
use App\Models\SharingInvitation;
use App\Services\Budget\BudgetCalculator;
use App\Services\Budget\ReadyToSpend;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BudgetOverview extends Component
{
    public function createBudget(BudgetCalculator $calculator): void
    {
        $user = auth()->user();
        assert($user !== null);

        $calculator->generateInitialBudget($user->id);
    }

    public function render(ReadyToSpend $rts): View
    {
        $user = auth()->user();
        assert($user !== null);

        $period = $user->currentBudgetPeriod();
        $allocations = $period?->allocations()->with('category')->get() ?? collect();
        $rtsData = $period ? $rts->compute($user->id) : [];
        $proposals = $period?->proposals()->where('status', 'pending')->get() ?? collect();

        $sharedInvitations = SharingInvitation::where('to_user_id', $user->id)
            ->where('resource_type', 'budget_category')
            ->active()
            ->with('fromUser')
            ->get();

        $sharedAllocations = collect();
        foreach ($sharedInvitations as $invitation) {
            $allocation = BudgetAllocation::where('category_id', $invitation->resource_id)
                ->with('category')
                ->first();
            if ($allocation) {
                $sharedAllocations->push([
                    'allocation' => $allocation,
                    'shared_by' => $invitation->fromUser?->name ?? 'Unknown',
                    'relation' => $invitation->relation->value,
                ]);
            }
        }

        return view('livewire.budget.budget-overview', [
            'period' => $period,
            'allocations' => $allocations,
            'rtsData' => $rtsData,
            'proposals' => $proposals,
            'hasBudget' => $user->budget !== null,
            'sharedAllocations' => $sharedAllocations,
        ]);
    }
}
