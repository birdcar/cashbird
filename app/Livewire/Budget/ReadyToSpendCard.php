<?php

declare(strict_types=1);

namespace App\Livewire\Budget;

use App\Services\Budget\ReadyToSpend;
use Illuminate\View\View;
use Livewire\Component;

class ReadyToSpendCard extends Component
{
    public function render(ReadyToSpend $rts): View
    {
        $user = auth()->user();
        assert($user !== null);

        $data = $rts->compute($user->id);
        $totalRemaining = collect($data)->sum('remaining');
        $totalAllocated = collect($data)->sum('allocated');
        $totalDailySafe = collect($data)->sum('daily_safe');
        $savingsPerDay = $rts->savingsContributionPerDay($user->id);

        return view('livewire.budget.ready-to-spend-card', [
            'totalRemaining' => $totalRemaining,
            'totalAllocated' => $totalAllocated,
            'totalDailySafe' => $totalDailySafe,
            'savingsPerDay' => $savingsPerDay,
            'hasData' => ! empty($data),
        ]);
    }
}
