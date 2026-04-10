<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Services\Debt\PayoffProjector;
use Illuminate\View\View;
use Livewire\Component;

class PayoffTimeline extends Component
{
    public int $monthlyExtra = 0;

    public function render(PayoffProjector $projector): View
    {
        $user = auth()->user();
        assert($user !== null);

        $debts = $user->debts()->where('status', 'active')->get();
        $schedule = $projector->withExtra($debts, $this->monthlyExtra);

        return view('livewire.debt.payoff-timeline', [
            'schedule' => $schedule,
            'milestones' => $schedule->milestones,
        ]);
    }
}
