<?php

declare(strict_types=1);

namespace App\Livewire\Insights;

use App\Models\Insight;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Insights')]
class InsightsFeed extends Component
{
    #[Computed]
    public function insights(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return $user->insights()->active()->orderByDesc('created_at')->get();
    }

    public function dismiss(string $insightId): void
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $insight = Insight::where('user_id', $user->id)->findOrFail($insightId);
        $insight->dismiss();

        unset($this->insights);
    }

    public function render(): View
    {
        return view('livewire.insights.insights-feed', [
            'insights' => $this->insights,
        ]);
    }
}
