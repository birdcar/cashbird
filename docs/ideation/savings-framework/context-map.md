# Context Map: savings-framework

**Phase**: 4
**Scout Confidence**: 91/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 19/20 | All 9 new files and 5 modified files identified. Budget overview 50/30/20 bar already exists — verify before modifying. |
| Pattern familiarity | 19/20 | All key patterns read. Minor gap: no existing CreateGoal-style form component to compare against — use AddManualDebt pattern. |
| Dependency awareness | 18/20 | Blast radius clear. sidebar DashboardTest, GenerateBudgetProposal scheduler, DebtDashboard route-only. |
| Edge case coverage | 19/20 | SavingsGoal has no scopeActive — use direct where clause. BudgetProposal whereHas chain validated. |
| Test strategy | 16/20 | Inner loop: filter on SavingsGoal/DebtSavings/GoalProgress. CategorySeeder needed for coordinator tests. Use Livewire::actingAs for page tests. |

## Key Patterns

- `app/Services/Budget/ReadyToSpend.php` — Pure service, `declare(strict_types=1)`, typed arrays with PHPDoc shapes
- `app/Services/Budget/SavingsStageAdvisor.php` — Same pattern, `currentStage(int $userId)`, `ensureSystemGoal(int $userId)`
- `app/Livewire/Debt/DebtDashboard.php` — Full-page: `#[Layout('components.layouts.app')]`, `#[Title]`, `abort_if`, `#[Computed]`
- `resources/views/livewire/debt/debt-dashboard.blade.php` — `space-y-8`, floating stats grid, white card list with divide-y, empty state
- `app/Jobs/GenerateBudgetProposal.php` — ShouldQueue, tries=3, backoff, PHP 8 constructor promotion, early returns
- `app/Models/SavingsGoal.php` — HasUuids, no scopeActive, query by `->where('status', GoalStatus::Active)`
- `app/Models/BudgetProposal.php` — `proposed_by` string, `changes` array cast, period→budget chain valid

## Dependencies

- `routes/web.php` — additive only
- `sidebar.blade.php` — DashboardTest file_get_contents assertion, won't break
- `GenerateBudgetProposal.php` — scheduler in console.php, add coordinator call at end of handle()
- `DebtDashboard.php` — route-only consumer, safe to modify
- `budget-overview.blade.php` — 50/30/20 bar already implemented (lines 47-59), verify before changing

## Conventions

- Services in `App\Services\Budget\`
- Auth: full-page = `abort_if(null, 401)`, partial = `assert(not null)`
- Money in cents, display via `number_format($val / 100, 2)`
- Route naming: `savings.index`, `savings.create`
- Sidebar: between Net Worth and separator, `phosphor-piggy-bank` icon
- Testing: `RefreshDatabase`, `actingAs($user, 'workos')`, `CategorySeeder` when needed
- CreateGoal follows `AddManualDebt` pattern (full-page form)
- `wire:navigate` on action anchors

## Risks

- **Budget overview already done**: 50/30/20 bar exists in budget-overview.blade.php. Verify before modifying.
- **No SavingsGoal::scopeActive()**: Use direct where clause, not a scope
- **CategorySeeder required**: DebtSavingsCoordinator needs "Transfer to Savings" category
- **GenerateBudgetProposal modification**: Call coordinator at end of handle(), keep testable in isolation
- **CreateGoal is full-page**: Follow AddManualDebt pattern, not inline modal
