<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Services\Categorization\SpendingAggregator;
use Carbon\Carbon;

class BudgetCalculator
{
    public function __construct(
        private readonly SpendingAggregator $aggregator,
        private readonly RecurringChargeDetector $detector,
    ) {}

    public function generateInitialBudget(int $userId): BudgetPeriod
    {
        $income = $this->estimateMonthlyIncome($userId);

        $budget = Budget::firstOrCreate(
            ['user_id' => $userId],
            ['projected_monthly_income' => $income],
        );

        $month = Carbon::now()->startOfMonth();

        $period = BudgetPeriod::create([
            'budget_id' => $budget->id,
            'month' => $month->toDateString(),
            'total_income' => $income,
            'total_allocated' => 0,
            'status' => 'active',
        ]);

        $this->detector->detect($userId);

        $this->allocateFromRecurring($period, $userId);
        $this->allocateDiscretionary($period, $userId);

        $period->update([
            'total_allocated' => $period->allocations()->sum('allocated_amount'),
        ]);

        return $period;
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

    private function allocateFromRecurring(BudgetPeriod $period, int $userId): void
    {
        $recurring = RecurringCharge::where('user_id', $userId)
            ->where('is_active', true)
            ->where('frequency', 'monthly')
            ->get();

        foreach ($recurring as $charge) {
            BudgetAllocation::create([
                'budget_period_id' => $period->id,
                'category_id' => $charge->category_id ?? Category::where('name', 'Uncategorized')->whereNull('parent_id')->value('id'),
                'allocated_amount' => $charge->average_amount,
                'is_fixed' => true,
                'lock_reason' => "recurring: \${$this->formatCents($charge->average_amount)}/mo detected",
            ]);
        }
    }

    private function allocateDiscretionary(BudgetPeriod $period, int $userId): void
    {
        $allocated = $period->allocations()->sum('allocated_amount');
        $discretionary = $period->total_income - $allocated;

        if ($discretionary <= 0) {
            return;
        }

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

        $totalHistorical = $candidates->sum('total_amount');

        if ($totalHistorical == 0) {
            $perCategory = (int) floor($discretionary / $candidates->count());
            foreach ($candidates as $cat) {
                BudgetAllocation::create([
                    'budget_period_id' => $period->id,
                    'category_id' => $cat['category_id'],
                    'allocated_amount' => $perCategory,
                ]);
            }
        } else {
            $allocated = 0;
            $candidateArray = $candidates->values()->all();
            foreach ($candidateArray as $i => $cat) {
                $ratio = $cat['total_amount'] / $totalHistorical;
                $amount = ($i === count($candidateArray) - 1)
                    ? $discretionary - $allocated
                    : (int) round($discretionary * $ratio);
                $amount = max(0, $amount);

                BudgetAllocation::create([
                    'budget_period_id' => $period->id,
                    'category_id' => $cat['category_id'],
                    'allocated_amount' => $amount,
                ]);

                $allocated += $amount;
            }
        }
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
