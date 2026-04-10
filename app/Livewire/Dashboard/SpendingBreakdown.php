<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Categorization\SpendingAggregator;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Component;

class SpendingBreakdown extends Component
{
    public function render(SpendingAggregator $aggregator): View
    {
        $userId = auth()->user()->id;
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $topCategories = $aggregator->topCategories($userId, $start, $end, 8);
        $monthOverMonth = $aggregator->monthOverMonth($userId, 6);

        return view('livewire.dashboard.spending-breakdown', [
            'topCategories' => $topCategories,
            'monthOverMonth' => $monthOverMonth,
            'currentMonth' => $start->format('F Y'),
        ]);
    }
}
