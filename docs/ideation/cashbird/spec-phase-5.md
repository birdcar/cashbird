# Implementation Spec: Cashbird - Phase 5: Debt Tracking & Payoff Strategy

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Build a debt tracking system that models all debt accounts (credit cards, payday loans, student loans, personal loans, credit card recovery plans) with APR-sorted avalanche payoff strategy. The system computes projected payoff timelines, tracks balance progression, and integrates with the budget engine (Phase 4) to allocate above-minimum payments to the highest-APR debt.

This phase runs parallel to Phase 3 — it only depends on Phase 2's account data, not categorized transactions. Debt accounts are identified by `accounts.type IN ('credit_card', 'loan')` from Teller data, supplemented by user-entered data for accounts not connected via Teller (e.g., payday loans from MoneyLion/Cleo that may not be in Teller).

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=Debt`

**Playground**: Test suite. Debt calculations are pure math — avalanche ordering, payoff projections, amortization schedules. Highly testable.

**Why this approach**: Payoff calculations are algorithmic with deterministic inputs/outputs. Tests validate the math without any external dependencies.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `app/Models/Debt.php` | Debt account with APR, minimum payment, balance |
| `app/Models/DebtPayment.php` | Historical payment records for tracking progress |
| `app/Services/Debt/AvalancheCalculator.php` | APR-sorted payoff algorithm with snowball rollup |
| `app/Services/Debt/PayoffProjector.php` | Projects payoff dates and total interest at current rate |
| `app/Services/Debt/DebtSynchronizer.php` | Syncs Teller account balances to debt records |
| `app/Livewire/Debt/DebtDashboard.php` | Debt overview with total owed, APR list, projections |
| `app/Livewire/Debt/DebtDetail.php` | Individual debt detail with payment history and projection |
| `app/Livewire/Debt/AddManualDebt.php` | Form for adding non-Teller debts (payday loans, etc.) |
| `app/Livewire/Debt/PayoffTimeline.php` | Visual timeline showing projected payoff milestones |
| `database/migrations/xxxx_create_debts_table.php` | Debts schema |
| `database/migrations/xxxx_create_debt_payments_table.php` | Payment tracking schema |
| `resources/views/livewire/debt/` | All debt Blade views |
| `tests/Feature/AvalancheCalculatorTest.php` | Payoff algorithm tests |
| `tests/Feature/PayoffProjectorTest.php` | Projection math tests |
| `tests/Feature/DebtSynchronizerTest.php` | Teller → debt sync tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `app/Models/User.php` | Add `debts()` relationship |
| `app/Models/Account.php` | Add `debt()` relationship for linked debts |
| `routes/web.php` | Add debt routes |
| `resources/views/livewire/layout/sidebar.blade.php` | Activate "Debt" nav link |
| `resources/views/livewire/dashboard.blade.php` | Add debt summary card |
| `app/Console/Kernel.php` | Schedule debt balance sync after account sync |

## Implementation Details

### Data Model

```sql
CREATE TABLE debts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    account_id UUID REFERENCES accounts(id) ON DELETE SET NULL, -- null for manual debts
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- credit_card, payday_loan, student_loan, personal_loan, auto_loan, mortgage, recovery_plan
    lender VARCHAR(255),
    current_balance BIGINT NOT NULL, -- cents, positive = owed
    original_balance BIGINT, -- cents, for tracking progress
    apr DECIMAL(6,3) NOT NULL, -- e.g., 24.990
    minimum_payment BIGINT NOT NULL, -- cents/month
    due_day INTEGER, -- day of month (1-28)
    is_in_recovery BOOLEAN DEFAULT false, -- for credit card recovery plans
    recovery_terms JSONB, -- {fixed_payment, duration_months, start_date} for recovery plans
    status VARCHAR(20) DEFAULT 'active', -- active, paid_off, closed
    paid_off_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_debts_user (user_id),
    INDEX idx_debts_apr (apr DESC)
);

CREATE TABLE debt_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    debt_id UUID NOT NULL REFERENCES debts(id) ON DELETE CASCADE,
    amount BIGINT NOT NULL, -- cents
    principal BIGINT, -- cents applied to principal
    interest BIGINT, -- cents applied to interest
    balance_after BIGINT NOT NULL, -- cents, running balance
    payment_date DATE NOT NULL,
    source VARCHAR(50) DEFAULT 'detected', -- detected (from transactions), manual
    transaction_id UUID REFERENCES transactions(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_debt_payments_debt (debt_id, payment_date DESC)
);
```

### Avalanche Calculator

**Overview**: Implements the avalanche debt payoff strategy — pay minimum on all debts, direct all extra to highest-APR debt. When a debt is paid off, roll its minimum into the next highest-APR debt (snowball rollup).

```php
class AvalancheCalculator
{
    public function calculatePayoffOrder(Collection $debts): Collection;
    public function allocateExtraPayment(Collection $debts, int $extraCents): array;
    public function projectPayoffSchedule(Collection $debts, int $monthlyExtra): PayoffSchedule;
}

class PayoffSchedule
{
    public Collection $debts; // ordered by payoff date
    public int $totalInterestPaid; // cents
    public int $monthsToDebtFree;
    public Carbon $projectedDebtFreeDate;
    public array $milestones; // [{debt_name, payoff_date, freed_amount}]
}
```

**Algorithm**:
```
1. Sort debts by APR descending
2. For each month until all paid:
   a. Apply minimum payment to each debt (subtract from balance, track interest)
   b. Apply extra payment to debt[0] (highest APR)
   c. If debt[0] balance <= 0:
      - Mark as paid off
      - Roll freed minimum into extra pool
      - Remove from list
   d. Record month's snapshot for timeline
3. Return schedule with milestones
```

**Special handling for recovery plans**: Debts with `is_in_recovery = true` have fixed payment terms. These are treated as locked (like non-negotiable bills in Phase 4) — their payment amount is fixed per the recovery agreement. They still sort by APR for prioritization of extra payments.

**Implementation steps**:
1. Implement APR-sorted ordering
2. Implement monthly payment simulation loop
3. Calculate interest per month: `balance * (apr / 12 / 100)`
4. Handle snowball rollup: freed minimums add to extra pool
5. Generate milestone array for timeline display
6. Special-case recovery plans (fixed terms)

**Feedback loop**:
- **Playground**: Create `tests/Feature/AvalancheCalculatorTest.php`
- **Experiment**: 
  - 2 debts: CC at 24.99% ($5000, $100 min) + payday at 200% ($500, $50 min), $300/mo extra → payday first despite lower balance
  - Single debt: $10,000 at 18%, $200 min, $0 extra → calculate exact months and interest
  - Recovery plan: $3000 at 0% (negotiated), $150/mo fixed for 20 months → not affected by avalanche ordering of extra
  - Payoff event: verify freed minimum rolls to next debt
- **Check command**: `php artisan test --filter=AvalancheCalculator`

### Payoff Projector

**Overview**: Projects payoff dates and total interest under different scenarios (current rate, with extra payments, snowball effect).

```php
class PayoffProjector
{
    public function atCurrentRate(Collection $debts): PayoffSchedule;
    public function withExtra(Collection $debts, int $monthlyExtra): PayoffSchedule;
    public function compareScenarios(Collection $debts, array $extras): array;
    public function debtFreeDate(Collection $debts, int $monthlyExtra): Carbon;
    public function totalInterestSaved(Collection $debts, int $monthlyExtra): int; // vs minimum only
}
```

**Implementation steps**:
1. `atCurrentRate()`: Run avalanche with $0 extra — baseline projection
2. `withExtra()`: Run avalanche with specified extra — improved projection
3. `compareScenarios()`: Run multiple extra amounts ($0, $100, $200, $500) and return comparison table
4. Display delta: months saved and interest saved vs baseline

**Feedback loop**:
- **Playground**: Create `tests/Feature/PayoffProjectorTest.php`
- **Experiment**: Known scenario: $10,000 at 20% APR, $200/min, compare $0 vs $100 vs $200 extra. Verify math against a known amortization calculator.
- **Check command**: `php artisan test --filter=PayoffProjector`

### Debt Synchronizer

**Overview**: Syncs Teller account balances to debt records. Creates debts from credit card and loan accounts automatically.

**Implementation steps**:
1. On account sync (Phase 2 `TransactionsSynced` event), check for credit/loan accounts
2. For each credit/loan account without a linked debt: create `Debt` record
3. For existing linked debts: update `current_balance` from account balance
4. Detect payments: new transactions that reduce debt balance → create `DebtPayment` record
5. Check for payoff: if balance reaches 0, mark `status = paid_off`

**Feedback loop**:
- **Playground**: Create `tests/Feature/DebtSynchronizerTest.php`
- **Experiment**: Test auto-creation from new credit card account, balance update on sync, payment detection from transaction matching, payoff detection
- **Check command**: `php artisan test --filter=DebtSynchronizer`

### Manual Debt Entry (Payday Loans, Recovery Plans)

**Overview**: Livewire form for adding debts not connected via Teller (MoneyLion, Cleo, recovery plans).

**Fields**: Name, type (dropdown), lender, current balance, original balance, APR, minimum payment, due day. For recovery plans: fixed payment amount, duration months, start date.

**Implementation steps**:
1. Create `AddManualDebt` Livewire component with form validation
2. Save to `debts` table with `account_id = null`
3. User manually updates balance monthly (or we add Teller connection later)
4. For recovery plans: set `is_in_recovery = true` and populate `recovery_terms` JSON

### Dashboard Components

**Overview**: Debt dashboard showing total owed, APR-sorted list, payoff timeline, progress tracking.

**Components**:
1. `DebtDashboard`: Summary card (total owed, average APR, projected debt-free date), list of all debts sorted by APR
2. `DebtDetail`: Individual debt with payment history chart, balance-over-time line, projected payoff
3. `PayoffTimeline`: Visual timeline with milestones (each debt payoff is a marker)

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/AvalancheCalculatorTest.php` | Ordering, payment allocation, snowball rollup, recovery plan handling |
| `tests/Feature/PayoffProjectorTest.php` | Projections, scenario comparison, interest calculation |
| `tests/Feature/DebtSynchronizerTest.php` | Auto-creation, balance sync, payment detection |

**Key test cases**:
- Avalanche sorts payday loan (200% APR) before credit card (24.99%)
- Snowball rollup: freed minimum adds to next debt's extra payment
- Recovery plan payments are fixed (not affected by avalanche extra allocation)
- Payoff projector: known $10K at 20% matches expected amortization
- Scenario comparison: $200 extra saves X months and $Y interest vs minimum-only
- Debt synchronizer creates debt from Teller credit card account
- Balance update reflects latest Teller sync
- Payoff detection when balance hits 0

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| AvalancheCalculator | Infinite loop | Minimum payment < monthly interest accrual | Never pays off | Detect and warn: "At current minimum, this debt grows. Extra payment required." |
| DebtSynchronizer | Stale Teller balance | Institution reports delayed data | Projection based on outdated balance | Show `last_synced_at`; allow manual balance override |
| ManualDebt | User enters wrong APR | Payday loan APR is confusing (fee-based vs annualized) | Projections are wrong | Provide APR calculator helper for fee-based loans: "If you pay $30 fee on $200 for 2 weeks, that's ~391% APR" |
| PayoffProjector | Income change invalidates plan | Job loss or raise not reflected | Projections diverge from reality | Monthly proposal (Phase 4) recalculates based on actual income; projections are estimates |

## Validation Commands

```bash
# Run migrations
php artisan migrate

# Run all Phase 5 tests
php artisan test --filter=Debt
php artisan test --filter=Avalanche
php artisan test --filter=Payoff

# Manual: verify debt creation from accounts
# php artisan tinker --execute="(new DebtSynchronizer)->syncForUser(User::first());"
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
