# Context Map: cashbird

**Phase**: 2
**Scout Confidence**: 82/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All files listed explicitly. One ambiguity: spec lists `app/Console/Kernel.php` as modified but Laravel 12 uses `routes/console.php` for scheduling. |
| Pattern familiarity | 17/20 | Phase 1 patterns all readable. Config, model, migration, test, Livewire component patterns clear. Minor gap: no existing Service class pattern. |
| Dependency awareness | 16/20 | `User.php` consumers identified (5 files). Modified files have low blast radius. Adding relationships/routes is additive. |
| Edge case coverage | 16/20 | Spec enumerates error scenarios thoroughly. Additional: UUID PKs in SQLite tests, `Console/Kernel.php` vs `routes/console.php`, mTLS in test env. |
| Test strategy | 15/20 | PHPUnit on SQLite in-memory. UUID PKs need `$table->uuid('id')->primary()` (PHP-side generation). `Http::fake()` for Teller API. |

**Phase 1 scores**: Scope 18, Pattern 12, Dependency 16, Edge case 12, Test 14 → 72/100

## Key Patterns

- `app/Models/Organization.php` — Model: `declare(strict_types=1)`, typed PHPDoc relations, static query helpers
- `database/migrations/create_organizations_table.php` — Migration: anonymous class, idempotency guard
- `config/workos.php` — Config: `declare(strict_types=1)`, section docblocks, `env()` with defaults
- `app/Livewire/Layout/AppShell.php` — Livewire: `declare(strict_types=1)`, `render()` returns view with layout
- `resources/views/livewire/dashboard.blade.php` — Views use `<x-layouts.app>` wrapper
- `tests/Feature/AuthenticationTest.php` — PHPUnit `test_` prefix, `RefreshDatabase`, `declare(strict_types=1)`
- `database/factories/UserFactory.php` — Factory: `definition()` returns `array<string, mixed>`

## Dependencies

- `app/Models/User.php` → consumed by tests, config, factory, seeder. Adding relationships is additive.
- `routes/web.php` → consumed by `bootstrap/app.php`. Adding routes is additive.
- `sidebar.blade.php` → `@include`'d by `app.blade.php`. Changing links is safe.
- `app/Console/Kernel.php` — **does not exist** in Laravel 12. Use `routes/console.php`.

## Conventions

- **Naming**: PascalCase models, kebab-case blades, snake_case DB columns
- **Imports**: PSR-4, `declare(strict_types=1)` everywhere
- **Types**: PHP 8.4, typed properties, constructor promotion, return types
- **Testing**: PHPUnit (not Pest), `tests/Feature/`, `RefreshDatabase`, `Http::fake()`
- **Config**: `declare(strict_types=1)`, `return []`, `env()` lookups
- **Migrations**: timestamp-prefixed filenames, anonymous classes
- **Scheduling**: `routes/console.php` (not Console/Kernel.php)
- **Package manager**: bun (JS), composer (PHP)

## Risks

- **`Console/Kernel.php` does not exist** — Use `routes/console.php` for scheduling
- **UUID PKs with SQLite tests** — Use `$table->uuid('id')->primary()`, not raw SQL
- **Categories migration before transactions** — FK reference requires ordering
- **mTLS cert in tests** — Don't validate cert at instantiation; `Http::fake()` bypasses HTTP
- **CSRF exclusion for webhook** — Add to `bootstrap/app.php` `withMiddleware()`
- **No existing Service class pattern** — First service; register singleton in AppServiceProvider
