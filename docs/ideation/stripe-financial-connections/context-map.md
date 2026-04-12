# Context Map: Stripe Financial Connections

**Phase**: 1
**Scout Confidence**: 86/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 19/20 | All files identified; blast radius (jobs, controller, routes) is extra-scope but understood |
| Pattern familiarity | 18/20 | TellerClient and TellerEnrollment patterns fully read; minor gap is exact Stripe SDK method signatures |
| Dependency awareness | 17/20 | Full blast radius mapped; jobs/controller will break post-migration but are Phase 2/3 scope |
| Edge case coverage | 16/20 | FK ordering in migration, integer vs UUID FK on user_id, Http::fake vs SDK mocking identified |
| Test strategy | 16/20 | PHPUnit patterns clear; Stripe SDK mocking approach is main open question |

## Key Patterns

- `app/Services/Teller/TellerClient.php` — declare(strict_types=1), readonly constructor params, typed Collections/arrays, $response->throw() on error, private request() helper. New client mirrors this but wraps StripeClient instead of HTTP.
- `app/Models/TellerEnrollment.php` — HasFactory+HasUuids traits, PHPDoc on factory, casts() method pattern, typed relationship PHPDocs. Connection model follows same patterns minus encrypted access_token.

## Dependencies

- `app/Models/Account.php` — consumed by → `SyncAllAccounts`, `SyncAccountTransactions`, `AccountList`, `AccountFactory`, `TransactionSyncTest`
- `app/Models/Transaction.php` — consumed by → `SyncAccountTransactions`, `TransactionList`, `TransactionFactory`, `TransactionSyncTest`, categorization listeners
- `app/Models/Institution.php` — consumed by → `TellerController` (Phase 2), `InstitutionFactory`
- `app/Models/User.php` — consumed by → routes/console.php schedules, jobs, Livewire components
- `app/Providers/AppServiceProvider.php` — consumed by → Laravel service container (singleton registration)

## Conventions

- **Naming**: strict_types=1, HasUuids for UUID PKs, casts() method (not $casts property)
- **Imports**: use statements grouped by Laravel framework, then app namespace
- **Error handling**: $response->throw() in HTTP clients, typed exceptions
- **Types**: PHPDoc @return on relationships with generics, typed constructor params with readonly
- **Testing**: PHPUnit, RefreshDatabase trait, test_ prefix, Http::fake for HTTP mocking (but Stripe SDK needs Mockery/PHPUnit mocks)

## Risks

- **Blast radius on jobs/controller**: SyncAllAccounts, SyncAccountTransactions, TellerController, and their tests will break after migration. Deferred to Phases 2/3.
- **Stripe SDK mocking**: Http::fake won't intercept Stripe SDK calls (uses Guzzle internally). Client constructor should accept optional StripeClient for test injection.
- **user_id FK type**: connections.user_id must be foreignId (bigint), not foreignUuid, to match users.id auto-increment.
- **TransactionSyncTest breakage**: Uses RefreshDatabase + TellerEnrollment factory — will fail immediately after migration.
