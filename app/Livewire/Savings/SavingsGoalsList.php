<?php

declare(strict_types=1);

namespace App\Livewire\Savings;

use App\Enums\GoalStatus;
use App\Enums\SavingsStage;
use App\Models\SavingsGoal;
use App\Services\Budget\GoalProgressCalculator;
use App\Services\Budget\SavingsStageAdvisor;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Savings')]
class SavingsGoalsList extends Component
{
    public function createSystemGoal(SavingsStageAdvisor $advisor): void
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $advisor->ensureSystemGoal($user->id);
    }

    /** @return Collection<int, SavingsGoal> */
    #[Computed]
    public function goals(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return SavingsGoal::where('user_id', $user->id)
            ->where('status', GoalStatus::Active)
            ->orderBy('priority')
            ->get();
    }

    #[Computed]
    public function stage(): SavingsStage
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return app(SavingsStageAdvisor::class)->currentStage($user->id);
    }

    #[Computed]
    public function systemGoal(): ?SavingsGoal
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return SavingsGoal::where('user_id', $user->id)
            ->where('is_system', true)
            ->where('status', GoalStatus::Active)
            ->first();
    }

    public function render(GoalProgressCalculator $calculator): View
    {
        $goals = $this->goals;

        $goalsWithProgress = $goals->map(fn (SavingsGoal $goal) => [
            'goal' => $goal,
            'progress' => $calculator->compute($goal),
        ]);

        return view('livewire.savings.savings-goals-list', [
            'goalsWithProgress' => $goalsWithProgress,
            'stage' => $this->stage,
            'systemGoal' => $this->systemGoal,
            'hasGoals' => $goals->isNotEmpty(),
        ]);
    }
}
