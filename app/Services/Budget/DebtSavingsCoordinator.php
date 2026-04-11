<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Enums\DebtStatus;
use App\Enums\GoalStatus;
use App\Models\BudgetAllocation;
use App\Models\BudgetProposal;
use App\Models\Category;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\User;

class DebtSavingsCoordinator
{
    public function checkAndPropose(int $userId): ?BudgetProposal
    {
        $activeDebts = Debt::where('user_id', $userId)->active()->count();
        if ($activeDebts > 0) {
            return null;
        }

        $paidOffDebts = Debt::where('user_id', $userId)
            ->where('status', DebtStatus::PaidOff)
            ->get();

        $freedTotal = $paidOffDebts->sum('minimum_payment');
        if ($freedTotal <= 0) {
            return null;
        }

        $existingProposal = BudgetProposal::whereHas('period.budget', fn ($q) => $q->where('user_id', $userId))
            ->where('proposed_by', 'debt_coordinator')
            ->where('status', 'pending')
            ->exists();

        if ($existingProposal) {
            return null;
        }

        $goal = SavingsGoal::where('user_id', $userId)
            ->where('status', GoalStatus::Active)
            ->orderBy('priority')
            ->first();

        if (! $goal) {
            return null;
        }

        $period = User::find($userId)?->currentBudgetPeriod();
        if (! $period) {
            return null;
        }

        $savingsCategory = Category::where('name', 'Transfer to Savings')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Savings & Investments'))
            ->first();

        if (! $savingsCategory) {
            return null;
        }

        $currentAllocation = BudgetAllocation::where('budget_period_id', $period->id)
            ->where('category_id', $savingsCategory->id)
            ->first();

        $oldAmount = $currentAllocation?->allocated_amount ?? 0;

        return BudgetProposal::create([
            'budget_period_id' => $period->id,
            'proposed_by' => 'debt_coordinator',
            'changes' => [[
                'category_id' => $savingsCategory->id,
                'category_name' => 'Transfer to Savings',
                'old_amount' => $oldAmount,
                'new_amount' => $oldAmount + $freedTotal,
                'rationale' => 'All debts are paid off! Redirecting $'.number_format($freedTotal / 100, 2)."/mo from debt payments toward your savings goal: {$goal->name}.",
            ]],
            'status' => 'pending',
        ]);
    }
}
