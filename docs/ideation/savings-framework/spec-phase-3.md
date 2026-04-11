# Implementation Spec: Savings Framework - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 3 adds net worth tracking. A `NetWorthCalculator` service aggregates all account balances (assets) and all debt balances (liabilities) into a single number. A scheduled job snapshots this monthly. The UI has two surfaces: a summary card on the dashboard and a dedicated `/net-worth` page with a historical trend chart and per-account breakdown.

This phase depends only on Phase 1 (the `NetWorthSnapshot` model) and can be built in parallel with Phase 2.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter="NetWorth"`

**Playground**: PHPUnit test suite + dev server for visual verification of the chart

**Why this approach**: The calculator is pure math (sum accounts, sum debts, subtract). Tests validate the aggregation. The chart needs visual verification.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `app/Services/NetWorthCalculator.php` | Computes current net worth from accounts and debts |
| `app/Jobs/SnapshotNetWorth.php` | Monthly scheduled job to create NetWorthSnapshot records |
| `app/Livewire/NetWorth/NetWorthDashboard.php` | Full-page Livewire component for /net-worth |
| `resources/views/livewire/net-worth/net-worth-dashboard.blade.php` | Detailed net worth page with chart and breakdown |
| `resources/views/livewire/dashboard/net-worth-card.blade.php` | Dashboard summary card component |
| `app/Livewire/Dashboard/NetWorthCard.php` | Livewire component for dashboard card |
| `tests/Feature/NetWorthCalculatorTest.php` | Tests for calculator |
| `tests/Feature/NetWorthDashboardTest.php` | Tests for the page |

### Modified Files

| File Path | Changes |
|---|---|
| `routes/web.php` | Add `/net-worth` route |
| `resources/views/livewire/dashboard.blade.php` | Add `<livewire:dashboard.net-worth-card />` below payoff timeline |
| `resources/views/livewire/layout/sidebar.blade.php` | Add "Net Worth" nav item with `phosphor-chart-line` icon |
| `app/Console/Kernel.php` or `routes/console.php` | Schedule `SnapshotNetWorth` job monthly |

## Implementation Details

### NetWorthCalculator

**Pattern to follow**: `app/Services/Budget/ReadyToSpend.php` (user-scoped calculation service)

```php
class NetWorthCalculator
{
    public function compute(int $userId): array
    {
        $accounts = Account::where('user_id', $userId)->get();
        $debts = Debt::where('user_id', $userId)->active()->get();

        $totalAssets = $accounts->sum('balance_current');  // cents
        $totalDebts = $debts->sum('current_balance');       // cents (already positive)
        $netWorth = $totalAssets - $totalDebts;

        return [
            'total_assets' => $totalAssets,
            'total_debts' => $totalDebts,
            'net_worth' => $netWorth,
            'breakdown' => [
                'accounts' => $accounts->map(fn ($a) => [
                    'name' => $a->name,
                    'type' => $a->type,
                    'balance' => $a->balance_current,
                ])->toArray(),
                'debts' => $debts->map(fn ($d) => [
                    'name' => $d->name,
                    'type' => $d->type,
                    'balance' => $d->current_balance,
                ])->toArray(),
            ],
        ];
    }

    public function trend(int $userId, int $months = 12): Collection
    {
        return NetWorthSnapshot::where('user_id', $userId)
            ->orderBy('month')
            ->take($months)
            ->get();
    }

    public function monthOverMonthChange(int $userId): ?int
    {
        $snapshots = NetWorthSnapshot::where('user_id', $userId)
            ->orderByDesc('month')
            ->take(2)
            ->get();

        if ($snapshots->count() < 2) return null;
        return $snapshots->first()->net_worth - $snapshots->last()->net_worth;
    }
}
```

**Key decisions**:
- Account `balance_current` can be negative (credit cards) — this is correct, as a negative balance on a credit card means you owe money, and it should reduce net worth. But wait — debts are tracked separately in the `debts` table. Credit card accounts that are also tracked as debts would be double-counted. Need to handle this: subtract only debts that are NOT linked to an account (have `account_id = null`), or subtract all debts and only count non-debt accounts as assets.
- Resolution: Count all accounts as assets (including credit cards — their balance_current reflects actual balance). Count only debts with `account_id IS NULL` (manually added debts) to avoid double-counting. Debts synced from accounts are already reflected in the account balance.

**Failure Modes**:

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| NetWorthCalculator | Double-counted debt | Debt synced from account AND counted separately | Inflated debt total | Only count debts where `account_id IS NULL` |
| NetWorthCalculator | Stale account balances | Teller sync hasn't run | Outdated net worth | Show "last synced" timestamp on the page |
| SnapshotNetWorth | Duplicate snapshot | Job runs twice in same month | Constraint violation | Use `firstOrCreate` on (user_id, month) |

### SnapshotNetWorth Job

**Pattern to follow**: existing scheduled jobs in `routes/console.php`

```php
class SnapshotNetWorth implements ShouldQueue
{
    public function handle(NetWorthCalculator $calculator): void
    {
        User::chunk(100, function ($users) use ($calculator) {
            foreach ($users as $user) {
                $data = $calculator->compute($user->id);
                NetWorthSnapshot::updateOrCreate(
                    ['user_id' => $user->id, 'month' => now()->startOfMonth()],
                    [
                        'total_assets' => $data['total_assets'],
                        'total_debts' => $data['total_debts'],
                        'net_worth' => $data['net_worth'],
                        'breakdown' => $data['breakdown'],
                    ]
                );
            }
        });
    }
}
```

Schedule: `Schedule::job(SnapshotNetWorth::class)->monthlyOn(1, '02:00');`

### Dashboard Net Worth Card

**Pattern to follow**: `resources/views/livewire/budget/ready-to-spend-card.blade.php` (prominent card with big number)

Shows: net worth amount (large), month-over-month change with arrow + color (sage for positive, terracotta for negative), "View details" link to /net-worth page.

### Net Worth Dashboard Page

**Pattern to follow**: `resources/views/livewire/debt/debt-dashboard.blade.php` (floating stats + detailed sections)

Layout:
1. Floating stats: Total Assets, Total Debts, Net Worth (hero size)
2. Month-over-month trend chart (bar chart, similar to spending-breakdown's month-by-month chart but showing net worth values)
3. Breakdown section: Accounts list and Debts list with individual balances

The trend chart reuses the same Blade bar-chart pattern from `spending-breakdown.blade.php`.

## Testing Requirements

| Test File | Coverage |
|---|---|
| `tests/Feature/NetWorthCalculatorTest.php` | Aggregation math, double-count prevention, empty accounts, month-over-month change |
| `tests/Feature/NetWorthDashboardTest.php` | Page renders, requires auth, shows data |

**Key test cases**:
- User with 2 accounts ($5k, $3k) and 1 manual debt ($2k) → net worth $6k
- User with credit card account (balance -$1k) synced as debt → debt NOT double-counted
- User with no accounts → net worth $0
- Month-over-month: two snapshots → correct delta
- Month-over-month: one snapshot → returns null
- SnapshotNetWorth job creates records for all users
- Dashboard card shows positive change in sage, negative in terracotta

## Validation Commands

```bash
php artisan test --compact --filter="NetWorth"
php artisan test --compact
vendor/bin/pint --dirty --format agent
bun run build
```
