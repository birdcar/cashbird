<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Models\SavingsGoal;
use Carbon\Carbon;

class GoalProgressCalculator
{
    /**
     * @return array{progress: int, remaining: int, projected_completion: ?Carbon, on_track: ?string, next_milestone: ?int, monthly_contribution: int}
     */
    public function compute(SavingsGoal $goal): array
    {
        $progress = $goal->target_amount > 0
            ? min(100, (int) round($goal->current_balance / $goal->target_amount * 100))
            : 0;

        $remaining = max(0, $goal->target_amount - $goal->current_balance);
        $monthlyContribution = $goal->monthly_contribution;

        $projectedCompletion = $monthlyContribution > 0
            ? now()->addMonths((int) ceil($remaining / $monthlyContribution))
            : null;

        $onTrack = $this->isOnTrack($goal, $progress);

        $nextMilestone = match (true) {
            $progress < 25 => 25,
            $progress < 50 => 50,
            $progress < 75 => 75,
            $progress < 100 => 100,
            default => null,
        };

        return [
            'progress' => $progress,
            'remaining' => $remaining,
            'projected_completion' => $projectedCompletion,
            'on_track' => $onTrack,
            'next_milestone' => $nextMilestone,
            'monthly_contribution' => $monthlyContribution,
        ];
    }

    private function isOnTrack(SavingsGoal $goal, int $progress): ?string
    {
        if (! $goal->target_date) {
            return null;
        }

        $totalMonths = $goal->created_at->diffInMonths($goal->target_date) ?: 1;
        $elapsedMonths = $goal->created_at->diffInMonths(now()) ?: 0;
        $expectedProgress = min(100, (int) round($elapsedMonths / $totalMonths * 100));

        if ($progress >= $expectedProgress) {
            return 'on_track';
        }

        // Within 10 percentage points of expected = at risk, otherwise behind
        return ($expectedProgress - $progress) <= 10 ? 'at_risk' : 'behind';
    }
}
