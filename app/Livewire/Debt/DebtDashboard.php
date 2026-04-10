<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Services\Debt\AvalancheCalculator;
use App\Services\Debt\PayoffProjector;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DebtDashboard extends Component
{
    public function render(AvalancheCalculator $calculator, PayoffProjector $projector): View
    {
        $user = auth()->user();
        assert($user !== null);

        $debts = $user->debts()->where('status', 'active')->get();
        $ordered = $calculator->calculatePayoffOrder($debts);
        $totalOwed = $debts->sum('current_balance');
        $totalMinimum = $debts->sum('minimum_payment');
        $avgApr = $debts->count() > 0
            ? round($debts->avg('apr'), 2)
            : 0;

        $schedule = $projector->atCurrentRate($debts);

        return view('livewire.debt.debt-dashboard', [
            'debts' => $ordered,
            'totalOwed' => $totalOwed,
            'totalMinimum' => $totalMinimum,
            'avgApr' => $avgApr,
            'schedule' => $schedule,
            'hasDebts' => $debts->isNotEmpty(),
        ]);
    }
}
