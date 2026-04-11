<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Report;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Report')]
class ReportView extends Component
{
    public Report $report;

    public function mount(Report $report): void
    {
        $user = auth()->user();
        abort_if($user === null, 401);
        abort_unless($report->user_id === $user->id, 403);

        $this->report = $report;
    }

    public function render(): View
    {
        return view('livewire.reports.report-view', [
            'report' => $this->report,
        ]);
    }
}
