<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Enums\BudgetCategory;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\SharingInvitation;
use App\Services\Budget\BudgetCalculator;
use App\Services\Budget\CategoryClassifier;
use App\Services\Budget\ReadyToSpend;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
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
        abort_if($user === null, 401);

        $calculator->generateInitialBudget($user->id);

        session()->flash('success', 'Your budget is ready!');
    }

    #[Computed]
    public function period(): ?BudgetPeriod
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return $user->currentBudgetPeriod();
    }

    /** @return Collection<int, BudgetAllocation> */
    #[Computed]
    public function allocations(): Collection
    {
        return $this->period?->allocations()->with('category')->get() ?? collect();
    }

    /** @return array<string, BudgetCategory> */
    #[Computed]
    public function classifications(): array
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        if (! $this->period) {
            return [];
        }

        return app(CategoryClassifier::class)->classifyForUser($user->id);
    }

    /** @return Collection<int, array{allocation: BudgetAllocation, shared_by: string, relation: string}> */
    #[Computed]
    public function sharedAllocations(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

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

        return $sharedInvitations
            ->filter(fn ($inv) => $allocationsByCategory->has($inv->resource_id))
            ->map(fn ($inv) => [
                'allocation' => $allocationsByCategory->get($inv->resource_id),
                'shared_by' => $inv->fromUser?->name ?? 'Unknown',
                'relation' => $inv->relation->value,
            ])
            ->values();
    }

    public function render(ReadyToSpend $rts): View
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $period = $this->period;
        $allocations = $this->allocations;
        $classifications = $this->classifications;

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
            'rtsData' => $period ? $rts->compute($user->id) : [],
            'proposals' => $period?->proposals()->where('status', 'pending')->get() ?? collect(),
            'hasBudget' => $user->budget !== null,
            'sharedAllocations' => $this->sharedAllocations,
            'needsPercent' => $needsPercent,
            'wantsPercent' => $wantsPercent,
            'savingsPercent' => $savingsPercent,
            'needsTotal' => $needsTotal,
            'wantsTotal' => $wantsTotal,
            'savingsTotal' => $savingsTotal,
        ]);
    }
}
