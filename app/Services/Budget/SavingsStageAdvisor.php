<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Enums\GoalStatus;
use App\Enums\SavingsStage;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class SavingsStageAdvisor
{
    /** $1,000 starter emergency fund floor (in cents) */
    private const STARTER_FUND_FLOOR = 100000;

    public function currentStage(int $userId): SavingsStage
    {
        $user = User::find($userId);

        if (! $user) {
            return SavingsStage::StarterEmergencyFund;
        }

        return $user->currentSavingsStage();
    }

    public function recommendedSavingsPercent(int $userId): int
    {
        $stage = $this->currentStage($userId);

        return match ($stage) {
            SavingsStage::StarterEmergencyFund => 10,
            SavingsStage::DebtPayoff => 10,
            SavingsStage::FullEmergencyFund => 30,
            SavingsStage::NamedGoals => 30,
        };
    }

    public function ensureSystemGoal(int $userId): ?SavingsGoal
    {
        $stage = $this->currentStage($userId);

        return match ($stage) {
            SavingsStage::StarterEmergencyFund => $this->ensureStarterFund($userId),
            SavingsStage::DebtPayoff => $this->ensureStarterFund($userId),
            SavingsStage::FullEmergencyFund => $this->ensureFullFund($userId),
            SavingsStage::NamedGoals => null,
        };
    }

    public function monthlyExpenses(int $userId): int
    {
        $since = Carbon::now()->subMonths(3)->startOfMonth();

        $totalExpenses = (int) abs(
            Transaction::where('user_id', $userId)
                ->where('amount', '<', 0)
                ->where('date', '>=', $since->toDateString())
                ->sum('amount')
        );

        return (int) round($totalExpenses / 3);
    }

    private function ensureStarterFund(int $userId): SavingsGoal
    {
        return SavingsGoal::firstOrCreate(
            [
                'user_id' => $userId,
                'is_system' => true,
                'name' => 'Emergency Fund',
            ],
            [
                'target_amount' => self::STARTER_FUND_FLOOR,
                'monthly_contribution' => 0,
                'priority' => 0,
                'status' => GoalStatus::Active,
            ]
        );
    }

    private function ensureFullFund(int $userId): SavingsGoal
    {
        $threeMonthExpenses = max($this->monthlyExpenses($userId) * 3, self::STARTER_FUND_FLOOR);

        $goal = SavingsGoal::where('user_id', $userId)
            ->where('is_system', true)
            ->where('name', 'Emergency Fund')
            ->first();

        if ($goal) {
            if ($goal->target_amount < $threeMonthExpenses) {
                $goal->update(['target_amount' => $threeMonthExpenses]);
            }

            return $goal;
        }

        return SavingsGoal::create([
            'user_id' => $userId,
            'name' => 'Emergency Fund',
            'target_amount' => $threeMonthExpenses,
            'monthly_contribution' => 0,
            'priority' => 0,
            'status' => GoalStatus::Active,
            'is_system' => true,
        ]);
    }
}
