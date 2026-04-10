<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\Debt\PayoffProjector;
use App\Services\Debt\PayoffSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DebtDetail extends Component
{
    use AuthorizesRequests;

    public Debt $debt;

    public function mount(Debt $debt): void
    {
        $this->authorize('view', $debt);
        $this->debt = $debt;
    }

    #[Computed]
    public function schedule(): PayoffSchedule
    {
        return app(PayoffProjector::class)->atCurrentRate(collect([$this->debt]));
    }

    /** @return array<int, array{extra: int, months: int, total_interest: int, debt_free_date: Carbon}> */
    #[Computed]
    public function scenarios(): array
    {
        return app(PayoffProjector::class)->compareScenarios(
            collect([$this->debt]),
            [0, 10000, 20000, 50000],
        );
    }

    /** @return Collection<int, DebtPayment> */
    #[Computed]
    public function payments(): Collection
    {
        return $this->debt->payments()->orderByDesc('payment_date')->get();
    }

    public function render(): View
    {
        return view('livewire.debt.debt-detail', [
            'debt' => $this->debt,
            'payments' => $this->payments,
            'schedule' => $this->schedule,
            'scenarios' => $this->scenarios,
        ]);
    }
}
