<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReportList extends Component
{
    #[Computed]
    public function reports(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return $user->reports()->orderByDesc('period_month')->get();
    }

    public function render(): View
    {
        return view('livewire.reports.report-list', [
            'reports' => $this->reports,
        ]);
    }
}
