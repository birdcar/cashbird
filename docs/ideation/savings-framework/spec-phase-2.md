# Implementation Spec: Savings Framework - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Phase 2 is the core budget engine overhaul. The `BudgetCalculator` changes from allocating 100% of income to spending categories to first reserving a savings percentage (default 20%), then distributing the remaining 80% across needs (62.5% of spending = 50% of income) and wants (37.5% of spending = 30% of income). The AI `BudgetAgent` gets a new responsibility: classifying categories into needs/wants/savings using the `CategoryClassification` model.

The `ReadyToSpend` service must subtract the savings contribution from available spending so the "safe to spend today" number reflects reality. The sidebar widget inherits this change automatically.

A new `SavingsStageAdvisor` service recommends the current savings stage and generates the appropriate system goal (starter emergency fund or full emergency fund).

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter="BudgetCalculator\|ReadyToSpend\|SavingsStage\|CategoryClassif"`

**Playground**: PHPUnit test suite + `php artisan tinker` for manual verification of calculations

**Why this approach**: Budget math is critical to get right — unit tests catch rounding errors, edge cases with zero income, and the needs/wants split calculations.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `app/Services/Budget/SavingsStageAdvisor.php` | Determines current savings stage, recommends goals, computes savings percentage |
| `app/Services/Budget/CategoryClassifier.php` | Orchestrates AI classification of categories, manages defaults and overrides |
| `app/Ai/Agents/CategoryClassifierAgent.php` | AI agent that classifies a batch of categories as needs/wants/savings |
| `tests/Feature/BudgetCalculatorSavingsTest.php` | Tests for the 50/30/20 budget engine changes |
| `tests/Feature/SavingsStageAdvisorTest.php` | Tests for stage determination and goal recommendations |
| `tests/Feature/CategoryClassifierTest.php` | Tests for AI classification and user overrides |

### Modified Files

| File Path | Changes |
|---|---|
| `app/Services/Budget/BudgetCalculator.php` | `generateInitialBudget` reserves savings allocation before discretionary distribution. New `calculateSplit()` method. |
| `app/Services/Budget/ReadyToSpend.php` | `compute()` subtracts daily savings contribution from `daily_safe`. New `savingsContribution()` helper. |
| `app/Ai/Agents/BudgetAgent.php` | System prompt updated to respect needs/wants classification and 50/30/20 targets. |
| `app/Livewire/Budget/BudgetOverview.php` | Pass 50/30/20 split totals to view. |
| `resources/views/livewire/budget/budget-overview.blade.php` | Show needs/wants/savings breakdown in stats area. |
| `resources/views/livewire/budget/ready-to-spend-card.blade.php` | Show savings contribution as a line item. |

## Implementation Details

### SavingsStageAdvisor

**Pattern to follow**: `app/Services/Budget/BudgetCalculator.php` (service with user-scoped methods)

```php
class SavingsStageAdvisor
{
    public function currentStage(int $userId): SavingsStage
    {
        // Evaluate: debts, emergency fund balance, monthly expenses
    }

    public function recommendedSavingsPercent(int $userId): int
    {
        // StarterEmergencyFund: 10% (minimum, rest to debt)
        // DebtPayoff: 10% (maintain starter fund, aggressive on debt)
        // FullEmergencyFund: 20% (standard)
        // NamedGoals: 20% (standard)
    }

    public function ensureSystemGoal(int $userId): ?SavingsGoal
    {
        // Create/update starter emergency fund ($1,000) or
        // full emergency fund (3 months expenses) based on stage
    }

    public function monthlyExpenses(int $userId): int
    {
        // 3-month average of spending (negative transactions)
    }
}
```

**Key decisions**:
- Savings percentage varies by stage: 10% during debt payoff (maintaining starter emergency fund), 20% otherwise
- System goals are auto-created but users can adjust target amounts
- Monthly expenses calculation reuses `estimateMonthlyIncome` logic but for outflows

**Feedback loop**:
- **Playground**: Create test with user at each stage (has debts + no emergency fund, has debts + $1k fund, no debts + small fund, no debts + full fund)
- **Experiment**: Assert correct stage, correct savings %, correct system goal creation
- **Check command**: `php artisan test --compact --filter=SavingsStageAdvisor`

### BudgetCalculator Modifications

**Pattern to follow**: existing `generateInitialBudget` flow in `app/Services/Budget/BudgetCalculator.php`

The existing flow is:
1. Estimate income → 2. Create budget/period → 3. Detect recurring → 4. Allocate fixed → 5. Allocate discretionary

New flow:
1. Estimate income → 2. Create budget/period → 3. Detect recurring → 4. **Determine savings percentage** → 5. **Create savings allocation** → 6. Allocate fixed (from remaining) → 7. Allocate discretionary (from remaining, with needs/wants weighting)

```php
public function calculateSplit(int $totalIncome, int $savingsPercent): array
{
    $savingsAmount = (int) floor($totalIncome * $savingsPercent / 100);
    $spendingPool = $totalIncome - $savingsAmount;
    // Needs get 62.5% of spending pool (= 50% of income at 20% savings)
    // Wants get 37.5% of spending pool (= 30% of income at 20% savings)
    $needsTarget = (int) floor($spendingPool * 625 / 1000);
    $wantsTarget = $spendingPool - $needsTarget;
    return compact('savingsAmount', 'needsTarget', 'wantsTarget');
}
```

**Key decisions**:
- Savings allocation is created as a `BudgetAllocation` against the "Savings & Investments > Transfer to Savings" category, with `is_fixed=true` and `lock_reason='savings_target'`
- Fixed recurring charges are subtracted from the appropriate pool (needs or wants) based on their category classification
- If fixed charges exceed the needs target, the overage comes from the wants pool (graceful degradation, not a hard error)

**Failure Modes**:

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| calculateSplit | Zero income | User has no deposits | Division produces 0 for all pools | Guard: if income <= 0, skip savings allocation |
| calculateSplit | Fixed charges exceed income | Rent + bills > total income | Negative discretionary pool | Guard: discretionary pool = max(0, remaining). Flash warning insight. |
| CategoryClassifier | AI returns invalid classification | LLM hallucination | Category unclassified | Default to "want" for unrecognized categories |

### ReadyToSpend Modifications

Add savings awareness to `compute()`:

```php
// After calculating remaining per category:
$savingsDaily = $this->dailySavingsContribution($userId);
// daily_safe is reduced by savings contribution
$dailySafe = max(0, floor($remaining / $daysRemaining) - $savingsDaily);
```

The sidebar widget and dashboard hero card automatically reflect this because they read from `ReadyToSpend::compute()`.

### CategoryClassifierAgent

**Pattern to follow**: `app/Ai/Agents/CategorizationAgent.php`

```php
#[UseSmartestModel]
class CategoryClassifierAgent implements Agent, HasStructuredOutput
{
    // Input: list of {category_id, category_path} pairs
    // Output: {classifications: [{category_id, classification: 'need'|'want'|'savings', rationale}]}
    // System prompt: "Classify household spending categories..."
    // Rules: Housing/utilities/groceries/insurance/transportation = needs
    //        Dining/entertainment/shopping/subscriptions = wants
    //        Savings/investments/debt payments = savings
}
```

### Budget Overview View Changes

The floating stats area gets a fourth stat showing the 50/30/20 breakdown:

```blade
<div class="grid gap-x-8 gap-y-2 sm:grid-cols-4">
    <div><!-- Monthly income --></div>
    <div><!-- Needs (50%) --></div>
    <div><!-- Wants (30%) --></div>
    <div><!-- Savings (20%) --></div>
</div>
```

### Ready-to-Spend Card Changes

Add a subtle line showing the savings contribution:

```blade
<p class="mt-1 text-sm text-sand-500">
    per day for the rest of this month
    <span class="text-sage-600">(includes $X/day toward savings)</span>
</p>
```

## Testing Requirements

| Test File | Coverage |
|---|---|
| `tests/Feature/BudgetCalculatorSavingsTest.php` | 50/30/20 split math, savings allocation creation, fixed charge overflow, zero income |
| `tests/Feature/SavingsStageAdvisorTest.php` | Stage determination for all 4 stages, system goal creation, monthly expenses calculation |
| `tests/Feature/CategoryClassifierTest.php` | AI classification, user override takes precedence, default-to-want fallback |

**Key test cases**:
- Income $5000 → savings $1000, needs $2500, wants $1500
- Income $0 → no savings allocation, no crash
- Fixed charges $3000 on $5000 income → needs overflow handled gracefully
- User overrides AI classification → override wins
- ReadyToSpend returns lower daily_safe after savings deduction
- Stage transitions: add debt → DebtPayoff, pay off all debts → FullEmergencyFund

## Validation Commands

```bash
php artisan test --compact --filter="BudgetCalculator\|ReadyToSpend\|SavingsStage\|CategoryClassif"
php artisan test --compact  # full suite to catch regressions
vendor/bin/pint --dirty --format agent
```
