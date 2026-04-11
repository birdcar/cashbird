<?php

declare(strict_types=1);

namespace App\Livewire\NetWorth;

use App\Services\NetWorthCalculator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Net Worth')]
class NetWorthDashboard extends Component
{
    public function render(NetWorthCalculator $calculator): View
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $data = $calculator->compute($user->id);
        $change = $calculator->monthOverMonthChange($user->id);
        $trend = $calculator->trend($user->id, 12);

        return view('livewire.net-worth.net-worth-dashboard', [
            'totalAssets' => $data['total_assets'],
            'totalDebts' => $data['total_debts'],
            'netWorth' => $data['net_worth'],
            'change' => $change,
            'trend' => $trend,
            'accounts' => $data['breakdown']['accounts'],
            'debts' => $data['breakdown']['debts'],
            'hasData' => $data['total_assets'] !== 0 || $data['total_debts'] !== 0,
        ]);
    }
}
