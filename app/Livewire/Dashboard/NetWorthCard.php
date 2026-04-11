<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\NetWorthCalculator;
use Illuminate\View\View;
use Livewire\Component;

class NetWorthCard extends Component
{
    public function render(NetWorthCalculator $calculator): View
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $data = $calculator->compute($user->id);
        $change = $calculator->monthOverMonthChange($user->id);

        return view('livewire.dashboard.net-worth-card', [
            'netWorth' => $data['net_worth'],
            'totalAssets' => $data['total_assets'],
            'totalDebts' => $data['total_debts'],
            'change' => $change,
            'hasData' => $data['total_assets'] !== 0 || $data['total_debts'] !== 0,
        ]);
    }
}
