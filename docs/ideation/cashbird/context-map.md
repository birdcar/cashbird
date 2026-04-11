# Context Map: cashbird

**Phase**: 7
**Scout Confidence**: 82/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 17/20 | All files identified; critical FK type and FGA SDK gaps documented |
| Pattern familiarity | 17/20 | Service, policy, Livewire, test patterns read; Http::fake() confirmed |
| Dependency awareness | 16/20 | BudgetOverview/AllocationEditor consumers mapped |
| Edge case coverage | 15/20 | workos_id vs integer id subject identity needs care |
| Test strategy | 17/20 | Http::fake() pattern confirmed; feature test structure clear |

## Phase 7 Key Corrections vs Spec

- **No FGA in WorkOS PHP SDK** — must use direct HTTP calls via Laravel's Http facade
- **`users.id` is integer** — `sharing_invitations` must use `foreignId()` not `foreignUuid()` for user FKs
- **FGA subject identity** — use `$user->workos_id` for FGA API calls, `$user->id` for local DB
- **Middleware registration** — use `bootstrap/app.php` `$middleware->alias()`, no Kernel.php exists
- **`config/workos.php` exists** — additive change only (add `fga` key)
- **`resource_id` is polymorphic UUID** — use `uuid()` column, not `foreignId()`

## Key Patterns

- `app/Services/Debt/DebtSynchronizer.php` — Service: constructor injection, DB::transaction, User parameter
- `app/Policies/DebtPolicy.php` — Policy: ownership check via user_id comparison
- `app/Livewire/Budget/AllocationEditor.php` — `#[Locked]` IDs, `ownedAllocation()` guard
- Http::fake() pattern confirmed in TellerWebhookTest

## Conventions

- Services in `app/Services/{Domain}/`
- Middleware registered in `bootstrap/app.php` via `$middleware->alias()`
- FGA subjects use `workos_id`, local FKs use integer `id`
- `foreignId()` for user FKs, `uuid()` for polymorphic resource IDs

## Risks

- **No FGA SDK** — must use Http facade directly, Http::fake() for tests
- **workos_id may be null** — FGAService must handle gracefully
- **AllocationEditor shared path** — needs FGA check in addition to ownership
- **Cache invalidation** — test env uses array cache, verify behavior
- **Unique constraint on sharing_invitations** — handle idempotent shares
