# Implementation Spec: Cashbird - Phase 4: Budget Engine

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Build the AI-driven zero-based budgeting system. The budget engine has three components:

1. **Budget Generation Agent** — Analyzes categorized spending history to produce an initial zero-based budget. Every dollar of projected income is assigned to a category. Non-negotiable bills are locked by the user; recurring charges are auto-detected and classified as fixed; everything else is AI-controlled.

2. **Ready-to-Spend Monitor** — A continuous background process that computes `(allocated - posted - pending) / days_remaining` per category and publishes updates via Redis for real-time UI.

3. **Monthly Proposal System** — At month end, the AI generates a diff-based budget adjustment proposal with per-line rationale. The user reviews and approves/rejects in a conversational UI.

Budgets are period-based (monthly). Each period has allocations per category. The AI agent uses spending aggregation data from Phase 3 to generate and adjust budgets.

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=Budget`

**Playground**: Test suite. Budget logic is math-heavy and testable without UI. Redis-based ready-to-spend is tested with Redis fakes.

**Why this approach**: Budget generation and ready-to-spend are pure logic with testable inputs/outputs. The AI agent is tested with mocked responses.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `app/Models/Budget.php` | Budget for a user (container for periods) |
| `app/Models/BudgetPeriod.php` | Monthly budget period with income/allocation totals |
| `app/Models/BudgetAllocation.php` | Per-category allocation within a period |
| `app/Models/RecurringCharge.php` | Auto-detected recurring transactions |
| `app/Models/BudgetProposal.php` | Monthly AI-generated budget adjustment proposal |
| `app/Agents/BudgetAgent.php` | AI agent for budget generation and proposals |
| `app/Services/Budget/RecurringChargeDetector.php` | Fingerprints recurring charges from transaction history |
| `app/Services/Budget/ReadyToSpend.php` | Real-time per-category available balance via Redis |
| `app/Services/Budget/BudgetCalculator.php` | Zero-based budget math (income allocation) |
| `app/Jobs/GenerateBudgetProposal.php` | Monthly scheduled job to produce budget proposal |
| `app/Jobs/UpdateReadyToSpend.php` | Recompute and publish ready-to-spend on new transactions |
| `app/Listeners/UpdateReadyToSpendOnTransaction.php` | Event listener for TransactionsCategorized |
| `app/Livewire/Budget/BudgetOverview.php` | Current period budget with allocations and progress |
| `app/Livewire/Budget/ReadyToSpendCard.php` | Real-time ready-to-spend display per category |
| `app/Livewire/Budget/ProposalReview.php` | Monthly proposal review/approve/reject UI |
| `app/Livewire/Budget/AllocationEditor.php` | Manual allocation adjustment (lock/unlock) |
| `database/migrations/xxxx_create_budgets_table.php` | Budgets schema |
| `database/migrations/xxxx_create_budget_periods_table.php` | Budget periods schema |
| `database/migrations/xxxx_create_budget_allocations_table.php` | Allocations schema |
| `database/migrations/xxxx_create_recurring_charges_table.php` | Recurring charges schema |
| `database/migrations/xxxx_create_budget_proposals_table.php` | Proposals schema |
| `resources/views/livewire/budget/` | All budget Blade views |
| `tests/Feature/RecurringChargeDetectorTest.php` | Recurring charge detection tests |
| `tests/Feature/BudgetCalculatorTest.php` | Zero-based budget math tests |
| `tests/Feature/ReadyToSpendTest.php` | Real-time calculation tests |
| `tests/Feature/BudgetAgentTest.php` | AI budget generation tests |
| `tests/Feature/BudgetProposalTest.php` | Proposal generation and approval tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `app/Models/User.php` | Add `budget()`, `currentBudgetPeriod()` relationships |
| `app/Providers/EventServiceProvider.php` | Register TransactionsCategorized → UpdateReadyToSpendOnTransaction |
| `routes/web.php` | Add budget routes |
| `resources/views/livewire/layout/sidebar.blade.php` | Activate "Budget" nav link |
| `resources/views/livewire/dashboard.blade.php` | Add ReadyToSpendCard summary to dashboard |
| `app/Console/Kernel.php` | Schedule GenerateBudgetProposal monthly |

## Implementation Details

### Data Model

```sql
CREATE TABLE budgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    projected_monthly_income BIGINT NOT NULL, -- cents
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id)
);

CREATE TABLE budget_periods (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    budget_id UUID NOT NULL REFERENCES budgets(id) ON DELETE CASCADE,
    month DATE NOT NULL, -- first day of month
    total_income BIGINT NOT NULL, -- cents, actual or projected
    total_allocated BIGINT NOT NULL DEFAULT 0, -- cents
    status VARCHAR(20) DEFAULT 'active', -- active, closed, draft
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(budget_id, month)
);

CREATE TABLE budget_allocations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    budget_period_id UUID NOT NULL REFERENCES budget_periods(id) ON DELETE CASCADE,
    category_id UUID NOT NULL REFERENCES categories(id),
    allocated_amount BIGINT NOT NULL, -- cents
    spent_amount BIGINT NOT NULL DEFAULT 0, -- cents, denormalized from transactions
    is_locked BOOLEAN DEFAULT false, -- user-locked non-negotiable
    is_fixed BOOLEAN DEFAULT false, -- auto-detected recurring
    lock_reason VARCHAR(255), -- "user-locked" or "recurring: $150/mo detected"
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(budget_period_id, category_id)
);

CREATE TABLE recurring_charges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    merchant_name VARCHAR(255) NOT NULL,
    category_id UUID REFERENCES categories(id),
    average_amount BIGINT NOT NULL, -- cents
    frequency VARCHAR(20) NOT NULL, -- monthly, quarterly, annual
    confidence DECIMAL(3,2) NOT NULL, -- 0.00-1.00
    last_seen_at DATE NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, merchant_name)
);

CREATE TABLE budget_proposals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    budget_period_id UUID NOT NULL REFERENCES budget_periods(id),
    proposed_by VARCHAR(20) DEFAULT 'ai', -- ai, user
    changes JSONB NOT NULL, -- [{category_id, old_amount, new_amount, rationale}]
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
    reviewed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Recurring Charge Detector

**Overview**: Scans transaction history to fingerprint recurring charges — same merchant, similar amount, regular interval.

```php
class RecurringChargeDetector
{
    public function detect(int $userId, int $lookbackMonths = 6): Collection;
    public function isRecurring(string $merchantName, Collection $transactions): ?RecurringPattern;
}

class RecurringPattern
{
    public string $merchantName;
    public int $averageAmount;     // cents
    public string $frequency;       // monthly, quarterly, annual
    public float $confidence;       // 0.0-1.0
    public Carbon $lastSeen;
}
```

**Detection algorithm**:
1. Group transactions by normalized merchant name
2. For each merchant with 3+ transactions in lookback window:
   - Calculate intervals between consecutive charges
   - Check if intervals cluster around 30 days (monthly), 90 (quarterly), or 365 (annual)
   - Compute confidence: `1.0 - (stddev(intervals) / mean(intervals))`
   - Threshold: confidence > 0.7 = recurring
3. Store detected patterns in `recurring_charges` table

**Implementation steps**:
1. Build merchant transaction grouping query
2. Implement interval analysis with std deviation calculation
3. Classify frequency based on mean interval (25-35 days = monthly, etc.)
4. Upsert results into `recurring_charges`
5. Run on initial budget creation and monthly before proposal generation

**Feedback loop**:
- **Playground**: Create `tests/Feature/RecurringChargeDetectorTest.php` with transaction factories
- **Experiment**: Test with Netflix-like charges ($15.99 on 1st of each month for 6 months → monthly, confidence ~0.95), irregular amounts (utility bills $80-$120 → monthly, lower confidence), one-off merchants (should not detect), quarterly charges (every 90 days)
- **Check command**: `php artisan test --filter=RecurringChargeDetector`

### Budget Calculator

**Overview**: Implements zero-based budgeting math — allocates every dollar of income to categories.

```php
class BudgetCalculator
{
    public function generateInitialBudget(int $userId): BudgetPeriod;
    public function allocateRemaining(BudgetPeriod $period): void;
    public function rebalance(BudgetPeriod $period, array $lockedAllocations): void;
}
```

**Zero-based algorithm**:
1. Start with projected monthly income
2. Subtract locked allocations (user-defined non-negotiables)
3. Subtract fixed allocations (auto-detected recurring charges)
4. Subtract minimum debt payments (from Phase 5 debt models)
5. Remaining = discretionary pool
6. AI agent allocates discretionary pool across remaining categories based on historical spending ratios, adjusted for debt payoff acceleration

**Implementation steps**:
1. Build income estimation from last 3 months of deposit transactions
2. Sum locked + fixed obligations
3. Calculate discretionary remainder
4. Pass to BudgetAgent for AI allocation of discretionary funds
5. Create BudgetPeriod with all allocations
6. Ensure total_allocated == total_income (zero-based constraint)

**Feedback loop**:
- **Playground**: Create `tests/Feature/BudgetCalculatorTest.php`
- **Experiment**: Test with $5000 income, $2000 locked (rent, car), $300 fixed (subscriptions), $200 debt minimums = $2500 discretionary. Verify total == income. Test with $0 discretionary (over-committed). Test rebalance after locking a new bill.
- **Check command**: `php artisan test --filter=BudgetCalculator`

### Budget Agent

**Overview**: Laravel AI SDK agent that makes allocation decisions for discretionary spending.

```php
class BudgetAgent extends Agent
{
    protected string $model = 'claude-sonnet-4-5-20250514';

    protected string $instructions = <<<'PROMPT'
    You are a personal finance budget advisor. Given a user's:
    - Monthly income
    - Locked obligations (non-negotiable bills)
    - Fixed charges (detected recurring)
    - Debt obligations and payoff strategy
    - Historical spending by category (last 3-6 months)
    - Discretionary pool available

    Allocate the discretionary pool across spending categories. Priorities:
    1. Debt payoff above minimums (avalanche strategy — highest APR first)
    2. Emergency fund contribution (if below 3 months expenses)
    3. Essential variable expenses (groceries, gas, healthcare)
    4. Discretionary spending (proportional to historical, reduced to fit)

    Return allocations as JSON: [{category_id, amount, rationale}]
    Every dollar must be allocated. Total must equal discretionary pool exactly.
    PROMPT;
}
```

**Key decisions**:
- Sonnet for cost efficiency — budget allocation is a reasoning task but not adversarial
- Include 3-6 months spending averages as context so AI can make proportional allocations
- Rationale strings stored for user transparency in proposal review

**Implementation steps**:
1. Create agent with structured output schema
2. Build context payload: income, locked items, fixed items, debts, spending history
3. Parse response into BudgetAllocation records
4. Validate total matches discretionary pool (retry if off)

**Feedback loop**:
- **Playground**: Create `tests/Feature/BudgetAgentTest.php` with AI SDK fakes
- **Experiment**: Test allocation with ample discretionary ($2500), tight discretionary ($200), zero discretionary (all locked/fixed). Verify total matches pool. Verify debt payoff prioritized over dining.
- **Check command**: `php artisan test --filter=BudgetAgent`

### Ready-to-Spend Monitor

**Overview**: Real-time per-category available balance computation published via Redis.

```php
class ReadyToSpend
{
    public function compute(int $userId, ?string $categoryId = null): array;
    public function publish(int $userId): void;
    public function dailySafeToSpend(int $userId, string $categoryId): int; // cents

    // Returns: [category_id => [allocated, spent, pending, remaining, daily_safe]]
}
```

**Computation**:
```
remaining = allocated_amount - sum(posted transactions) - sum(pending transactions)
daily_safe = remaining / days_remaining_in_period
```

**Implementation steps**:
1. Query current period allocations
2. Sum posted + pending transactions per category for current period
3. Calculate remaining and daily safe amounts
4. Store in Redis hash: `cashbird:rts:{user_id}` with per-category JSON
5. Publish via Redis pub/sub channel `cashbird:rts-update:{user_id}`
6. Livewire component subscribes via polling (every 30s) or Echo/Redis broadcasting

**Feedback loop**:
- **Playground**: Create `tests/Feature/ReadyToSpendTest.php` with Redis fake
- **Experiment**: Test with $500 allocated, $200 spent, $50 pending, 15 days left = $250 remaining, ~$16.67/day. Test edge: 0 days left (last day of month). Test negative remaining (over budget). Test with no transactions (full allocation available).
- **Check command**: `php artisan test --filter=ReadyToSpend`

### Monthly Proposal System

**Overview**: At month end, AI generates diff-based budget adjustments for the next period.

**Implementation steps**:
1. Scheduled job runs on 28th of each month (or last day)
2. Gather: current period actuals vs allocations, detected spending pattern changes, new/removed recurring charges, debt balance changes
3. BudgetAgent generates proposal as diff: `[{category, old_amount, new_amount, rationale}]`
4. Store as `BudgetProposal` with `status = pending`
5. Notify user (in-app notification via database notifications)
6. User reviews in `ProposalReview` Livewire component
7. On approve: create next month's `BudgetPeriod` with adjusted allocations
8. On reject: carry forward current month's allocations (or allow manual edit)

**Feedback loop**:
- **Playground**: Create `tests/Feature/BudgetProposalTest.php`
- **Experiment**: Test proposal generation with overspent categories (suggest reduction), underspent categories (suggest reallocation to debt), new recurring charge detected (add as fixed). Test approval creates correct next period. Test rejection carries forward.
- **Check command**: `php artisan test --filter=BudgetProposal`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/RecurringChargeDetectorTest.php` | Detection algorithm, confidence scoring |
| `tests/Feature/BudgetCalculatorTest.php` | Zero-based math, rebalancing |
| `tests/Feature/BudgetAgentTest.php` | AI allocation with mocked responses |
| `tests/Feature/ReadyToSpendTest.php` | Computation, Redis publishing |
| `tests/Feature/BudgetProposalTest.php` | Proposal generation, approval, rejection |

**Key test cases**:
- Zero-based constraint: total_allocated == total_income always
- Locked allocations cannot be modified by AI
- Recurring detection correctly identifies monthly Netflix-like charges
- Ready-to-spend updates within 60s of new transaction event
- Proposal diff accurately reflects over/under spending
- Budget creation from scratch with no history (AI gets equal distribution)
- Rebalance after user locks a new category

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| BudgetAgent | AI allocates more than pool | Hallucinated amounts | Zero-based constraint violated | Validate total matches pool; retry up to 3x; fall back to proportional historical split |
| BudgetAgent | AI API unavailable | Provider outage | Cannot generate budget or proposal | Carry forward previous period allocations; notify user |
| RecurringChargeDetector | False positive | Variable charge looks recurring | Fixed allocation for non-recurring expense | Confidence threshold (0.7); user can unlock/override |
| RecurringChargeDetector | Missed recurring | Irregular timing (e.g., billed on weekdays) | Not classified as fixed, AI treats as discretionary | Review detected charges quarterly; allow user to manually mark as recurring |
| ReadyToSpend | Stale Redis data | Transaction event lost or Redis restart | Dashboard shows wrong available amount | TTL on Redis key (5 min); recompute on dashboard load if stale |
| MonthlyProposal | Generated too early | Job scheduled before month-end transactions settle | Proposal based on incomplete data | Run on 28th with 3-day grace; regenerate if user requests |

## Validation Commands

```bash
# Run migrations
php artisan migrate

# Run all Phase 4 tests
php artisan test --filter=Budget
php artisan test --filter=RecurringCharge
php artisan test --filter=ReadyToSpend

# Verify Redis
php artisan tinker --execute="Redis::connection()->ping();"

# Manual: generate initial budget
# php artisan tinker --execute="(new BudgetCalculator)->generateInitialBudget(User::first()->id);"
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
