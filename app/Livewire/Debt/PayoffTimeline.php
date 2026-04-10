<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Services\Debt\PayoffProjector;
use App\Services\Debt\PayoffSchedule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PayoffTimeline extends Component
{
    public int $monthlyExtra = 0;

    #[Computed]
    public function schedule(): PayoffSchedule
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $debts = $user->debts()->projectable()->get();

        return app(PayoffProjector::class)->withExtra($debts, $this->monthlyExtra);
    }

    public function render(): View
    {
        $schedule = $this->schedule;

        return view('livewire.debt.payoff-timeline', [
            'schedule' => $schedule,
            'milestones' => $schedule->milestones,
        ]);
    }
}
