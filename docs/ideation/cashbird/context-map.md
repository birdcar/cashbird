# Context Map: cashbird

**Phase**: 5
**Scout Confidence**: 88/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All new/modified files identified; `app/Console/Kernel.php` doesn't exist — `routes/console.php` is the scheduler |
| Pattern familiarity | 19/20 | Read Budget/Account/Transaction models, BudgetCalculator service, Livewire components, migrations, factories, tests — patterns clear |
| Dependency awareness | 17/20 | `Account.php` and `User.php` consumers identified; `TransactionsSynced` event + `AppServiceProvider` listener pattern confirmed |
| Edge case coverage | 17/20 | Spec failure modes documented; UUID/foreignId mismatch is a critical catch the spec gets wrong |
| Test strategy | 17/20 | Clear: `php artisan test --filter=Debt`, test structure mirrors `BudgetCalculatorTest.php`, factories required |

## Prior Phase Key Risks (Phase 4)

- Console/Kernel.php does not exist — use `routes/console.php` and `Schedule::` facade
- BudgetAgent namespace: use `App\Ai\Agents` not `App\Agents`
- JSONB column — use `->json()` which normalizes to jsonb on pgsql

## Key Patterns

- `app/Models/Budget.php` — `declare(strict_types=1)`, `HasFactory` with typed generic docblock, `HasUuids`, `$fillable`, `casts()` method, typed relationship return annotations
- `app/Services/Budget/BudgetCalculator.php` — readonly constructor injection, `declare(strict_types=1)`, cents-as-integers
- `app/Livewire/Budget/BudgetOverview.php` — Full-page Livewire: `#[Layout('components.layouts.app')]`, service injection in `render()`, `auth()->user()` + `assert($user !== null)`
- `app/Livewire/Budget/ReadyToSpendCard.php` — Sub-component: no `#[Layout]`, service injection in `render()`
- `database/migrations/2026_04_10_200001_create_budgets_table.php` — `uuid('id')->primary()`, `foreignId('user_id')->constrained()->cascadeOnDelete()` (NOT foreignUuid)
- `app/Events/TransactionsSynced.php` — Event: `use Dispatchable`, constructor with public typed property
- `app/Providers/AppServiceProvider.php` — Event listener registration in `boot()`
- `tests/Feature/BudgetCalculatorTest.php` — `RefreshDatabase`, `setUp()` + `$this->user`, PHPUnit class-based

## Dependencies

- `app/Models/User.php` — consumed by all Livewire components, services. Adding `debts()` is additive.
- `app/Models/Account.php` — consumed by sync jobs, Teller controller. Adding `debt()` is additive.
- `routes/web.php` — sidebar `route('debt.*')` calls. Must use `debt.index`, `debt.show`, `debt.create` naming.
- `resources/views/livewire/layout/sidebar.blade.php` — has disabled "Debt" placeholder to activate.
- `routes/console.php` — scheduler. Adding debt sync follows existing pattern.
- `app/Providers/AppServiceProvider.php` — adding event listener follows existing pattern.

## Conventions

- **Naming**: Models singular PascalCase; services in `App\Services\Debt\`; Livewire in `App\Livewire\Debt\`; views in `resources/views/livewire/debt/`
- **Imports**: `declare(strict_types=1);`, full class imports, grouped by type
- **Error handling**: `assert($user !== null)` for auth guard, exceptions propagate
- **Types**: PHPDoc `@return` on relationships with both generic args, `casts()` method not `$casts` property, money as `int` (cents)
- **Testing**: `tests/Feature/`, `RefreshDatabase`, PHPUnit class-based, `php artisan test --compact --filter=ClassName`
- **Migrations**: `uuid('id')->primary()` for UUID PK; `foreignId('user_id')` for User FK; `foreignUuid('account_id')` for Account FK

## Critical Risk: Spec SQL vs Actual Schema

The spec SQL uses `user_id UUID` — this is **wrong**. Users table uses integer PK. Use `foreignId('user_id')` not `foreignUuid`. Account and Transaction FKs are UUID (`foreignUuid`).

## Risks

- **`user_id` FK type mismatch** — Use `foreignId('user_id')` not `foreignUuid`
- **`TransactionsSynced` hook** — Register in `AppServiceProvider::boot()`, create Listener class
- **`routes/console.php`** — Spec says `app/Console/Kernel.php` which doesn't exist
- **Infinite loop in AvalancheCalculator** — Add loop guard (max 600 iterations) and detect negative amortization
- **`PayoffSchedule`** — Use `readonly class` with public properties
- **Livewire view naming** — `debt-dashboard.blade.php` for `DebtDashboard` component
- **`recovery_terms` JSONB** — Use `->json('recovery_terms')->nullable()`, cast to `'array'`
