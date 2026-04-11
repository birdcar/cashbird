<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InsightsSummary extends Component
{
    #[Computed]
    public function insights(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return new Collection;
        }

        return $user->insights()->active()->orderByDesc('created_at')->limit(3)->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.insights-summary', [
            'insights' => $this->insights,
        ]);
    }
}
