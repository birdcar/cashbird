<?php

declare(strict_types=1);

namespace App\Livewire\Savings;

use App\Enums\GoalStatus;
use App\Models\SavingsGoal;
use App\Services\Budget\GoalProgressCalculator;
use App\Services\Budget\SavingsStageAdvisor;
use Illuminate\View\View;
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

    public function render(GoalProgressCalculator $calculator, SavingsStageAdvisor $advisor): View
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $goals = SavingsGoal::where('user_id', $user->id)
            ->where('status', GoalStatus::Active)
            ->orderBy('priority')
            ->get();

        $goalsWithProgress = $goals->map(fn (SavingsGoal $goal) => [
            'goal' => $goal,
            'progress' => $calculator->compute($goal),
        ]);

        $stage = $advisor->currentStage($user->id);
        $systemGoal = $advisor->ensureSystemGoal($user->id);

        return view('livewire.savings.savings-goals-list', [
            'goalsWithProgress' => $goalsWithProgress,
            'stage' => $stage,
            'systemGoal' => $systemGoal,
            'hasGoals' => $goals->isNotEmpty(),
        ]);
    }
}
