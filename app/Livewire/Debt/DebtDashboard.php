<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Enums\DebtStatus;
use App\Models\Debt;
use App\Services\Budget\SavingsStageAdvisor;
use App\Services\Debt\AvalancheCalculator;
use App\Services\Debt\PayoffProjector;
use App\Services\Debt\PayoffSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Debt')]
class DebtDashboard extends Component
{
    /** @return Collection<int, Debt> */
    #[Computed]
    public function debts(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return $user->debts()->active()->get();
    }

    #[Computed]
    public function schedule(): PayoffSchedule
    {
        return app(PayoffProjector::class)->atCurrentRate($this->debts);
    }

    public function render(AvalancheCalculator $avalanche, SavingsStageAdvisor $advisor): View
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $debts = $this->debts;
        $ordered = $avalanche->calculatePayoffOrder($debts);

        $savingsStage = null;
        if ($debts->isEmpty()) {
            $hasPaidOffDebts = $user->debts()->where('status', DebtStatus::PaidOff)->exists();
            if ($hasPaidOffDebts) {
                $savingsStage = $advisor->currentStage($user->id);
            }
        }

        return view('livewire.debt.debt-dashboard', [
            'debts' => $ordered,
            'totalOwed' => $debts->sum('current_balance'),
            'totalMinimum' => $debts->sum('minimum_payment'),
            'avgApr' => $debts->count() > 0 ? round($debts->avg('apr'), 2) : 0,
            'schedule' => $this->schedule,
            'hasDebts' => $debts->isNotEmpty(),
            'savingsStage' => $savingsStage,
        ]);
    }
}
