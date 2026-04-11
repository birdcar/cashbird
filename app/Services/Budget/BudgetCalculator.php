<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Enums\BudgetCategory;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Services\Categorization\SpendingAggregator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetCalculator
{
    public function __construct(
        private readonly SpendingAggregator $aggregator,
        private readonly RecurringChargeDetector $detector,
        private readonly SavingsStageAdvisor $advisor,
        private readonly CategoryClassifier $classifier,
    ) {}

    public function generateInitialBudget(int $userId): BudgetPeriod
    {
        return DB::transaction(function () use ($userId) {
            $income = $this->estimateMonthlyIncome($userId);

            $budget = Budget::firstOrCreate(
                ['user_id' => $userId],
                ['projected_monthly_income' => $income],
            );

            $month = Carbon::now()->startOfMonth();

            $period = BudgetPeriod::firstOrCreate([
                'budget_id' => $budget->id,
                'month' => $month->toDateString(),
            ], [
                'total_income' => $income,
                'total_allocated' => 0,
                'status' => 'active',
            ]);

            $this->detector->detect($userId);
            $this->advisor->ensureSystemGoal($userId);

            $savingsPercent = $this->advisor->recommendedSavingsPercent($userId);
            $split = $this->calculateSplit($income, $savingsPercent);

            $this->allocateSavings($period, $split['savingsAmount']);
            $this->allocateFromRecurring($period, $userId);
            $this->allocateDiscretionary($period, $userId, $split);
            $this->allocateRemaining($period);

            $period->update([
                'total_allocated' => $period->allocations()->sum('allocated_amount'),
            ]);

            return $period;
        });
    }

    /**
     * Calculate the 50/30/20 budget split.
     *
     * Default: 50% needs, 30% savings, 20% wants.
     * The needs/wants ratio can be adjusted via $needsPercent (percentage of total income).
     *
     * @return array{savingsAmount: int, needsTarget: int, wantsTarget: int}
     */
    public function calculateSplit(int $totalIncome, int $savingsPercent, int $needsPercent = 50): array
    {
        if ($totalIncome <= 0) {
            return ['savingsAmount' => 0, 'needsTarget' => 0, 'wantsTarget' => 0];
        }

        $savingsAmount = (int) floor($totalIncome * $savingsPercent / 100);
        $needsTarget = (int) floor($totalIncome * $needsPercent / 100);
        $wantsTarget = $totalIncome - $savingsAmount - $needsTarget;

        return compact('savingsAmount', 'needsTarget', 'wantsTarget');
    }

    public function allocateRemaining(BudgetPeriod $period): void
    {
        $allocated = $period->allocations()->sum('allocated_amount');
        $remaining = $period->total_income - $allocated;

        if ($remaining <= 0) {
            return;
        }

        $uncategorized = Category::where('name', 'Uncategorized')
            ->whereNull('parent_id')
            ->first();

        if ($uncategorized) {
            BudgetAllocation::updateOrCreate(
                ['budget_period_id' => $period->id, 'category_id' => $uncategorized->id],
                ['allocated_amount' => $remaining],
            );
        }

        $period->update([
            'total_allocated' => $period->allocations()->sum('allocated_amount'),
        ]);
    }

    public function rebalance(BudgetPeriod $period, array $lockedAllocationIds): void
    {
        $locked = $period->allocations()
            ->whereIn('id', $lockedAllocationIds)
            ->sum('allocated_amount');

        $fixed = $period->allocations()
            ->where('is_fixed', true)
            ->whereNotIn('id', $lockedAllocationIds)
            ->sum('allocated_amount');

        $remaining = $period->total_income - $locked - $fixed;

        $unlocked = $period->allocations()
            ->where('is_locked', false)
            ->where('is_fixed', false)
            ->get();

        if ($unlocked->isEmpty() || $remaining <= 0) {
            return;
        }

        $perCategory = (int) floor($remaining / $unlocked->count());
        $leftover = $remaining - ($perCategory * $unlocked->count());

        foreach ($unlocked as $i => $allocation) {
            $amount = $perCategory + ($i === 0 ? $leftover : 0);
            $allocation->update(['allocated_amount' => $amount]);
        }

        $period->update([
            'total_allocated' => $period->allocations()->sum('allocated_amount'),
        ]);
    }

    public function estimateMonthlyIncome(int $userId, int $lookbackMonths = 3): int
    {
        $since = Carbon::now()->subMonths($lookbackMonths)->startOfMonth();

        $totalIncome = Transaction::where('user_id', $userId)
            ->where('amount', '>', 0)
            ->where('date', '>=', $since->toDateString())
            ->sum('amount');

        return (int) round($totalIncome / $lookbackMonths);
    }

    private function allocateSavings(BudgetPeriod $period, int $savingsAmount): void
    {
        if ($savingsAmount <= 0) {
            return;
        }

        $savingsCategory = Category::where('name', 'Transfer to Savings')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Savings & Investments'))
            ->first();

        if (! $savingsCategory) {
            return;
        }

        BudgetAllocation::updateOrCreate(
            ['budget_period_id' => $period->id, 'category_id' => $savingsCategory->id],
            [
                'allocated_amount' => $savingsAmount,
                'is_fixed' => true,
                'lock_reason' => 'savings_target',
            ],
        );
    }

    private function allocateFromRecurring(BudgetPeriod $period, int $userId): void
    {
        $recurring = RecurringCharge::where('user_id', $userId)
            ->where('is_active', true)
            ->where('frequency', 'monthly')
            ->get();

        $fallbackCategoryId = Category::where('name', 'Uncategorized')
            ->whereNull('parent_id')
            ->value('id');

        foreach ($recurring as $charge) {
            $categoryId = $charge->category_id ?? $fallbackCategoryId;
            BudgetAllocation::updateOrCreate(
                ['budget_period_id' => $period->id, 'category_id' => $categoryId],
                [
                    'allocated_amount' => $charge->average_amount,
                    'is_fixed' => true,
                    'lock_reason' => "recurring: \${$this->formatCents($charge->average_amount)}/mo detected",
                ],
            );
        }
    }

    /**
     * @param  array{savingsAmount: int, needsTarget: int, wantsTarget: int}  $split
     */
    private function allocateDiscretionary(BudgetPeriod $period, int $userId, array $split): void
    {
        $allocated = $period->allocations()->sum('allocated_amount');
        $discretionary = $period->total_income - $allocated;

        if ($discretionary <= 0) {
            return;
        }

        $classifications = $this->classifier->classifyForUser($userId);

        $start = Carbon::now()->subMonths(3)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $topSpending = $this->aggregator->topCategories($userId, $start, $end, 20);

        $existingCategoryIds = $period->allocations()->pluck('category_id')->toArray();

        $candidates = $topSpending->filter(
            fn ($row) => ! in_array($row['category_id'], $existingCategoryIds)
        );

        if ($candidates->isEmpty()) {
            $this->allocateRemaining($period);

            return;
        }

        $needsCandidates = $candidates->filter(
            fn ($row) => ($classifications[$row['category_id']] ?? BudgetCategory::Want) === BudgetCategory::Need
        );
        $wantsCandidates = $candidates->filter(
            fn ($row) => ($classifications[$row['category_id']] ?? BudgetCategory::Want) === BudgetCategory::Want
        );

        $fixedAllocated = $period->allocations()->where('is_fixed', true)->sum('allocated_amount');
        $needsRemaining = max(0, $split['needsTarget'] - $fixedAllocated);
        $wantsRemaining = max(0, $discretionary - $needsRemaining);

        $this->distributeProportionally($period, $needsCandidates, $needsRemaining);
        $this->distributeProportionally($period, $wantsCandidates, $wantsRemaining);
    }

    private function distributeProportionally(BudgetPeriod $period, Collection $candidates, int $pool): void
    {
        if ($candidates->isEmpty() || $pool <= 0) {
            return;
        }

        $totalHistorical = $candidates->sum('total_amount');

        if ($totalHistorical === 0) {
            $perCategory = (int) floor($pool / $candidates->count());
            foreach ($candidates as $cat) {
                BudgetAllocation::create([
                    'budget_period_id' => $period->id,
                    'category_id' => $cat['category_id'],
                    'allocated_amount' => $perCategory,
                ]);
            }

            return;
        }

        $allocated = 0;
        $candidateArray = $candidates->values()->all();
        foreach ($candidateArray as $i => $cat) {
            $ratio = $cat['total_amount'] / $totalHistorical;
            $amount = ($i === count($candidateArray) - 1)
                ? $pool - $allocated
                : (int) round($pool * $ratio);
            $amount = max(0, $amount);

            BudgetAllocation::create([
                'budget_period_id' => $period->id,
                'category_id' => $cat['category_id'],
                'allocated_amount' => $amount,
            ]);

            $allocated += $amount;
        }
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
