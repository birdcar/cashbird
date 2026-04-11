# Implementation Spec: Savings Framework - Phase 4

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Phase 4 is the user-facing layer: savings goals UI, goal progress calculations, debt-to-savings coordination, and budget overview changes showing the 50/30/20 breakdown visually.

The savings goals page follows the existing pattern (Livewire full-page component with Phosphor icons, warm palette). Goal progress is computed from `current_balance` vs `target_amount` with projected completion dates based on monthly contribution rate.

The debt-to-savings coordination hooks into the existing `AvalancheCalculator`: when a debt is paid off and no remaining debts exist, the system generates a `BudgetProposal` redirecting the total freed payment amount toward the active savings goal.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter="SavingsGoal\|DebtSavings\|GoalProgress"`

**Playground**: PHPUnit test suite + dev server for visual verification of goals page

**Why this approach**: Goal progress math and debt-coordination logic need test coverage. The UI needs visual verification for progress bars and status indicators.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `app/Services/Budget/GoalProgressCalculator.php` | Computes progress %, projected completion, on-track status |
| `app/Services/Budget/DebtSavingsCoordinator.php` | Generates proposal when all debts paid off |
| `app/Livewire/Savings/SavingsGoalsList.php` | Full-page Livewire component for /savings |
| `app/Livewire/Savings/CreateGoal.php` | Livewire component for goal creation form |
| `resources/views/livewire/savings/savings-goals-list.blade.php` | Goals list with progress bars |
| `resources/views/livewire/savings/create-goal.blade.php` | Goal creation form |
| `tests/Feature/GoalProgressCalculatorTest.php` | Tests for progress calculations |
| `tests/Feature/DebtSavingsCoordinatorTest.php` | Tests for debt-to-savings proposal generation |
| `tests/Feature/SavingsGoalsListTest.php` | Tests for the Livewire page |

### Modified Files

| File Path | Changes |
|---|---|
| `routes/web.php` | Add `/savings` and `/savings/create` routes |
| `resources/views/livewire/layout/sidebar.blade.php` | Add "Savings" nav item with `phosphor-piggy-bank` icon |
| `resources/views/livewire/budget/budget-overview.blade.php` | Add needs/wants/savings summary with visual indicator |
| `app/Jobs/GenerateBudgetProposal.php` | Add check: if all debts paid off, generate savings redirect proposal |
| `app/Livewire/Debt/DebtDashboard.php` | Show savings stage context when all debts paid |

## Implementation Details

### GoalProgressCalculator

```php
class GoalProgressCalculator
{
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

    private function isOnTrack(SavingsGoal $goal, int $progress): ?bool
    {
        if (!$goal->target_date) return null;

        $totalMonths = $goal->created_at->diffInMonths($goal->target_date) ?: 1;
        $elapsedMonths = $goal->created_at->diffInMonths(now()) ?: 0;
        $expectedProgress = min(100, (int) round($elapsedMonths / $totalMonths * 100));

        return $progress >= $expectedProgress;
    }
}
```

**Key decisions**:
- `on_track` is `null` when no target date is set (open-ended goals)
- `projected_completion` is `null` when monthly contribution is 0 (goal is paused or unfunded)
- Milestone markers at 25/50/75/100% for the goal-gradient effect

**Feedback loop**:
- **Playground**: Create goals at various states: 0%, 40%, 75%, 100%, no target date, zero contribution
- **Experiment**: Assert correct progress %, on-track status, projected dates
- **Check command**: `php artisan test --compact --filter=GoalProgress`

### DebtSavingsCoordinator

**Pattern to follow**: `app/Jobs/GenerateBudgetProposal.php` (existing proposal generation)

```php
class DebtSavingsCoordinator
{
    public function checkAndPropose(int $userId): ?BudgetProposal
    {
        $activeDebts = Debt::where('user_id', $userId)->active()->count();
        if ($activeDebts > 0) return null;

        // All debts paid off — calculate freed payment total
        $paidOffDebts = Debt::where('user_id', $userId)
            ->where('status', DebtStatus::PaidOff)
            ->get();

        $freedTotal = $paidOffDebts->sum('minimum_payment');
        if ($freedTotal <= 0) return null;

        // Check if we already proposed this
        $existingProposal = BudgetProposal::whereHas('period.budget', fn ($q) =>
            $q->where('user_id', $userId)
        )->where('proposed_by', 'debt_coordinator')
          ->where('status', 'pending')
          ->exists();

        if ($existingProposal) return null;

        // Find the active savings goal (highest priority)
        $goal = SavingsGoal::where('user_id', $userId)
            ->where('status', GoalStatus::Active)
            ->orderBy('priority')
            ->first();

        if (!$goal) return null;

        // Generate proposal: redirect freed debt payments to savings
        $period = User::find($userId)->currentBudgetPeriod();
        if (!$period) return null;

        $savingsCategory = Category::where('name', 'Transfer to Savings')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Savings & Investments'))
            ->first();

        if (!$savingsCategory) return null;

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
                'rationale' => "All debts are paid off! Redirecting $" . number_format($freedTotal / 100, 2) . "/mo from debt payments toward your savings goal: {$goal->name}.",
            ]],
            'status' => 'pending',
        ]);
    }
}
```

**Failure Modes**:

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| DebtSavingsCoordinator | No savings category exists | Category seeder not run | Can't create proposal | Guard: return null if category not found |
| DebtSavingsCoordinator | Duplicate proposals | Coordinator runs multiple times | User sees repeat proposals | Check for existing pending proposal from `debt_coordinator` |
| GoalProgressCalculator | Division by zero | target_amount = 0 | Crash | Guard: progress = 0 when target <= 0 |

### Savings Goals Page

**Pattern to follow**: `resources/views/livewire/debt/debt-dashboard.blade.php` (list page with floating stats and action button)

Layout:
1. Header: "Savings" with "Add Goal" button
2. Current stage indicator — a badge showing the household's savings stage (e.g., "Building emergency fund") with a brief explanation
3. Goals list — each goal as a card with: name, progress bar (amber fill), percentage, amount ($X of $Y), projected completion date, on-track status badge (sage = on track, amber = at risk, terracotta = behind)
4. Empty state: Phosphor piggy-bank icon, "No savings goals yet. Let's set one up."

Goal creation form:
- Name (text)
- Target amount ($)
- Target date (optional date picker)
- Priority (auto-assigned, can reorder)

AI recommendation: When the savings goals list is empty or only has system goals, show a recommendation from the `SavingsStageAdvisor`: "Based on your finances, we recommend starting with a $1,000 emergency fund." with a one-click "Create this goal" button.

### Budget Overview Changes

Add a visual 50/30/20 indicator to the stats area. Below the existing stats (income, budgeted, unbudgeted), add a stacked bar showing the actual needs/wants/savings split compared to the 50/30/20 target:

```blade
<div class="mt-4">
    <div class="flex items-center justify-between text-xs text-sand-500 mb-1">
        <span>Needs {{ $needsPercent }}%</span>
        <span>Wants {{ $wantsPercent }}%</span>
        <span>Savings {{ $savingsPercent }}%</span>
    </div>
    <div class="flex h-2.5 overflow-hidden rounded-full">
        <div class="bg-amber-400" style="width: {{ $needsPercent }}%"></div>
        <div class="bg-sage-400" style="width: {{ $wantsPercent }}%"></div>
        <div class="bg-terracotta-400" style="width: {{ $savingsPercent }}%"></div>
    </div>
    <div class="flex items-center justify-between text-xs text-sand-400 mt-1">
        <span>Target: 50%</span>
        <span>30%</span>
        <span>20%</span>
    </div>
</div>
```

## Testing Requirements

| Test File | Coverage |
|---|---|
| `tests/Feature/GoalProgressCalculatorTest.php` | Progress math, on-track detection, milestones, edge cases |
| `tests/Feature/DebtSavingsCoordinatorTest.php` | Proposal generation when debts cleared, duplicate prevention, no-goal scenario |
| `tests/Feature/SavingsGoalsListTest.php` | Page renders, requires auth, shows goals, empty state |

**Key test cases**:
- Goal 50% complete → progress = 50, next milestone = 75
- Goal with no target date → on_track = null
- Goal with zero contribution → projected_completion = null
- All debts paid off + active goal → proposal created
- All debts paid off + no goals → no proposal
- Some debts still active → no proposal
- Duplicate coordinator run → no duplicate proposal
- Savings page renders goals sorted by priority
- Empty savings page shows recommendation based on stage

## Validation Commands

```bash
php artisan test --compact --filter="SavingsGoal\|DebtSavings\|GoalProgress"
php artisan test --compact
vendor/bin/pint --dirty --format agent
bun run build
```
