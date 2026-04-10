<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

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

        return view('livewire.budget.budget-overview', [
            'period' => $period,
            'allocations' => $allocations,
            'rtsData' => $rtsData,
            'proposals' => $proposals,
            'hasBudget' => $user->budget !== null,
        ]);
    }
}
