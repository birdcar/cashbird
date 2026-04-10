<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Models\Debt;
use App\Services\Debt\PayoffProjector;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DebtDetail extends Component
{
    public Debt $debt;

    public function mount(Debt $debt): void
    {
        $user = auth()->user();
        assert($user !== null);

        abort_unless($debt->user_id === $user->id, 403);
        $this->debt = $debt;
    }

    public function render(PayoffProjector $projector): View
    {
        $payments = $this->debt->payments()->orderByDesc('payment_date')->get();
        $schedule = $projector->atCurrentRate(collect([$this->debt]));
        $scenarios = $projector->compareScenarios(
            collect([$this->debt]),
            [0, 10000, 20000, 50000],
        );

        return view('livewire.debt.debt-detail', [
            'debt' => $this->debt,
            'payments' => $payments,
            'schedule' => $schedule,
            'scenarios' => $scenarios,
        ]);
    }
}
