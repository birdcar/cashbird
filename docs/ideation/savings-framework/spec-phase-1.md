# Implementation Spec: Savings Framework - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 1 establishes the data layer for all subsequent phases. Three new models (SavingsGoal, NetWorthSnapshot, CategoryClassification), two new enums (SavingsStage, BudgetCategory), and their corresponding migrations, factories, and seeders.

The key architectural decision is storing category classifications (needs/wants/savings) as a separate `category_classifications` table rather than adding a column to the `categories` table. This keeps the system categories clean and allows per-user overrides — the AI assigns defaults, but users can reclassify a category for their household.

All monetary values follow the existing convention: integers in cents, bigInteger columns.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter="SavingsGoal\|NetWorthSnapshot\|CategoryClassification\|SavingsStage"`

**Playground**: PHPUnit test suite

**Why this approach**: Phase 1 is entirely models, migrations, and enums — the test runner validates schema, relationships, and factory correctness.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `app/Enums/SavingsStage.php` | Enum: StarterEmergencyFund, DebtPayoff, FullEmergencyFund, NamedGoals |
| `app/Enums/BudgetCategory.php` | Enum: Need, Want, Savings |
| `app/Enums/GoalStatus.php` | Enum: Active, Completed, Paused |
| `app/Models/SavingsGoal.php` | Model for named savings goals with target amount, target date, current balance |
| `app/Models/NetWorthSnapshot.php` | Model for monthly net worth snapshots |
| `app/Models/CategoryClassification.php` | Model for per-user needs/wants/savings classification of categories |
| `database/migrations/2026_04_11_000001_create_savings_goals_table.php` | Migration |
| `database/migrations/2026_04_11_000002_create_net_worth_snapshots_table.php` | Migration |
| `database/migrations/2026_04_11_000003_create_category_classifications_table.php` | Migration |
| `database/factories/SavingsGoalFactory.php` | Factory |
| `database/factories/NetWorthSnapshotFactory.php` | Factory |
| `database/factories/CategoryClassificationFactory.php` | Factory |
| `tests/Feature/SavingsGoalModelTest.php` | Tests for SavingsGoal model |
| `tests/Feature/NetWorthSnapshotModelTest.php` | Tests for NetWorthSnapshot model |
| `tests/Feature/CategoryClassificationModelTest.php` | Tests for CategoryClassification model |

### Modified Files

| File Path | Changes |
|---|---|
| `app/Models/User.php` | Add `savingsGoals()`, `netWorthSnapshots()`, `categoryClassifications()` relationships. Add `currentSavingsStage()` method. |

## Implementation Details

### SavingsGoal Model

**Pattern to follow**: `app/Models/Debt.php` (similar structure: user-owned, UUID PK, monetary amounts, status enum)

```php
// Schema
Schema::create('savings_goals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');                    // "Emergency Fund", "Vacation"
    $table->bigInteger('target_amount');        // cents
    $table->bigInteger('current_balance')->default(0); // cents
    $table->date('target_date')->nullable();    // optional deadline
    $table->integer('monthly_contribution')->default(0); // auto-calculated cents/month
    $table->integer('priority')->default(0);    // ordering
    $table->string('status')->default('active'); // GoalStatus enum
    $table->boolean('is_system')->default(false); // true for auto-generated emergency fund
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->index('user_id');
    $table->index(['user_id', 'status']);
});
```

**Key decisions**:
- `is_system` flag distinguishes AI-recommended goals (emergency fund) from user-created goals
- `monthly_contribution` is computed from `(target_amount - current_balance) / months_remaining` but stored for quick access
- `priority` determines allocation order when budget has limited savings capacity

### NetWorthSnapshot Model

**Pattern to follow**: `app/Models/Report.php` (monthly user-owned records)

```php
Schema::create('net_worth_snapshots', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('month');                      // first of month
    $table->bigInteger('total_assets');          // sum of account balances (cents)
    $table->bigInteger('total_debts');           // sum of debt balances (cents)
    $table->bigInteger('net_worth');             // assets - debts (cents, can be negative)
    $table->json('breakdown')->nullable();       // per-account/debt detail
    $table->timestamps();
    $table->unique(['user_id', 'month']);
});
```

### CategoryClassification Model

```php
Schema::create('category_classifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
    $table->string('classification');           // BudgetCategory enum: need/want/savings
    $table->boolean('is_ai_assigned')->default(true);
    $table->timestamps();
    $table->unique(['user_id', 'category_id']);
});
```

### Enums

```php
// SavingsStage — the household's current financial phase
enum SavingsStage: string {
    case StarterEmergencyFund = 'starter_emergency_fund';  // Build $1,000
    case DebtPayoff = 'debt_payoff';                        // Pay off all non-mortgage debt
    case FullEmergencyFund = 'full_emergency_fund';         // Build 3-6 months expenses
    case NamedGoals = 'named_goals';                        // User-defined goals
}

// BudgetCategory — spending classification
enum BudgetCategory: string {
    case Need = 'need';
    case Want = 'want';
    case Savings = 'savings';
}

// GoalStatus
enum GoalStatus: string {
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';
}
```

### User Model Changes

Add `currentSavingsStage()` that evaluates:
1. Has active debts and emergency fund < $1,000 → `StarterEmergencyFund`
2. Has active debts and emergency fund >= $1,000 → `DebtPayoff`
3. No active debts and emergency fund < 3 months expenses → `FullEmergencyFund`
4. No active debts and emergency fund >= 3 months expenses → `NamedGoals`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|---|---|
| `tests/Feature/SavingsGoalModelTest.php` | CRUD, relationships, factory, status transitions, completion |
| `tests/Feature/NetWorthSnapshotModelTest.php` | Creation, uniqueness constraint, breakdown JSON |
| `tests/Feature/CategoryClassificationModelTest.php` | AI assignment, user override, uniqueness constraint |

**Key test cases**:
- SavingsGoal factory creates valid records
- SavingsGoal `completed_at` is set when status transitions to completed
- NetWorthSnapshot unique constraint on (user_id, month) rejects duplicates
- CategoryClassification unique constraint on (user_id, category_id)
- User `currentSavingsStage()` returns correct stage for each scenario
- User relationships return correct collections

## Validation Commands

```bash
php artisan migrate --force
php artisan test --compact --filter="SavingsGoal\|NetWorthSnapshot\|CategoryClassification"
vendor/bin/pint --dirty --format agent
```
