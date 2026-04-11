<?php

declare(strict_types=1);

namespace App\Livewire\NetWorth;

use App\Models\NetWorthSnapshot;
use App\Services\NetWorthCalculator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Net Worth')]
class NetWorthDashboard extends Component
{
    /**
     * @return array{total_assets: int, total_debts: int, net_worth: int, breakdown: array{accounts: list<array{name: string, type: string, balance: int}>, debts: list<array{name: string, type: string, balance: int}>}}
     */
    #[Computed]
    public function netWorthData(): array
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return app(NetWorthCalculator::class)->compute($user->id);
    }

    #[Computed]
    public function change(): ?int
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return app(NetWorthCalculator::class)->monthOverMonthChange($user->id);
    }

    /** @return Collection<int, NetWorthSnapshot> */
    #[Computed]
    public function trend(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return app(NetWorthCalculator::class)->trend($user->id, 12);
    }

    public function render(): View
    {
        $data = $this->netWorthData;

        return view('livewire.net-worth.net-worth-dashboard', [
            'totalAssets' => $data['total_assets'],
            'totalDebts' => $data['total_debts'],
            'netWorth' => $data['net_worth'],
            'change' => $this->change,
            'trend' => $this->trend,
            'accounts' => $data['breakdown']['accounts'],
            'debts' => $data['breakdown']['debts'],
            'hasData' => $data['total_assets'] !== 0 || $data['total_debts'] !== 0,
        ]);
    }
}
