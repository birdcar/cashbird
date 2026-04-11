<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Enums\BudgetCategory;
use App\Models\BudgetAllocation;
use App\Models\SharingInvitation;
use App\Services\Budget\BudgetCalculator;
use App\Services\Budget\CategoryClassifier;
use App\Services\Budget\ReadyToSpend;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Budget')]
class BudgetOverview extends Component
{
    public function createBudget(BudgetCalculator $calculator): void
    {
        $user = auth()->user();
        assert($user !== null);

        $calculator->generateInitialBudget($user->id);

        session()->flash('success', 'Your budget is ready!');
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

        $categoryIds = $sharedInvitations->pluck('resource_id');
        $allocationsByCategory = BudgetAllocation::whereIn('category_id', $categoryIds)
            ->whereHas('period.budget', fn ($q) => $q->whereIn('user_id', $sharedInvitations->pluck('from_user_id')->unique()))
            ->with('category')
            ->get()
            ->keyBy('category_id');

        $sharedAllocations = $sharedInvitations
            ->filter(fn ($inv) => $allocationsByCategory->has($inv->resource_id))
            ->map(fn ($inv) => [
                'allocation' => $allocationsByCategory->get($inv->resource_id),
                'shared_by' => $inv->fromUser?->name ?? 'Unknown',
                'relation' => $inv->relation->value,
            ])
            ->values();

        $classifier = app(CategoryClassifier::class);
        $classifications = $period ? $classifier->classifyForUser($user->id) : [];

        $needsTotal = 0;
        $wantsTotal = 0;
        $savingsTotal = 0;
        foreach ($allocations as $allocation) {
            if ($allocation->lock_reason === 'savings_target') {
                $savingsTotal += $allocation->allocated_amount;
            } elseif (($classifications[$allocation->category_id] ?? BudgetCategory::Want) === BudgetCategory::Need) {
                $needsTotal += $allocation->allocated_amount;
            } else {
                $wantsTotal += $allocation->allocated_amount;
            }
        }

        $totalAllocated = $needsTotal + $wantsTotal + $savingsTotal;
        $needsPercent = $totalAllocated > 0 ? (int) round($needsTotal / $totalAllocated * 100) : 0;
        $wantsPercent = $totalAllocated > 0 ? (int) round($wantsTotal / $totalAllocated * 100) : 0;
        $savingsPercent = $totalAllocated > 0 ? 100 - $needsPercent - $wantsPercent : 0;

        return view('livewire.budget.budget-overview', [
            'period' => $period,
            'allocations' => $allocations->filter(fn ($a) => $a->lock_reason !== 'savings_target'),
            'rtsData' => $rtsData,
            'proposals' => $proposals,
            'hasBudget' => $user->budget !== null,
            'sharedAllocations' => $sharedAllocations,
            'needsPercent' => $needsPercent,
            'wantsPercent' => $wantsPercent,
            'savingsPercent' => $savingsPercent,
            'needsTotal' => $needsTotal,
            'wantsTotal' => $wantsTotal,
            'savingsTotal' => $savingsTotal,
        ]);
    }
}
