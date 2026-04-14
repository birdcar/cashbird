# Context Map: Stripe Financial Connections

**Phase**: 3 (extended from Phase 2)
**Scout Confidence**: 95/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 20/20 | All files read; new/modified/deleted files fully understood |
| Pattern familiarity | 20/20 | Stripe client API, webhook verification, test patterns all read |
| Dependency awareness | 19/20 | Full event pipeline mapped; minor gap is Stripe `Event` object exact shape |
| Edge case coverage | 19/20 | CSRF exclusion method confirmed, auth error path confirmed, `starting_after` cursor pagination confirmed |
| Test strategy | 17/20 | Mock pattern for StripeFinancialConnectionsClient confirmed; webhook test needs raw payload construction |

## Key Patterns (Phase 1–2)

- TellerController: no constructor injection, client injected as method param, `$request->user()` + assert, `UniqueConstraintViolationException` catch
- ConnectAccount: pure render container, passes config to view, `@push('scripts')` for JS
- Routes: all in `auth:workos` group, named routes
- Test mocking: `$this->mock(StripeFinancialConnectionsClient::class, ...)` — resolves singleton via service container
- Test HTTP: `FinancialConnectionsFlowTest` uses `actingAs($user, 'workos')` consistently

## Key Patterns (Phase 3 — new)

### StripeFinancialConnectionsClient API

All methods confirmed available:

| Method | Signature | Return |
|---|---|---|
| `getAccount` | `(string $accountId)` | `StripeAccount` |
| `getBalances` | `(string $accountId)` | `array{current: int\|null, available: int\|null, type: string}` |
| `listTransactions` | `(string $accountId, ?string $startingAfter = null)` | `Collection<int, StripeTransaction>` |
| `refreshTransactions` | `(string $accountId)` | `StripeAccount` |
| `disconnect` | `(string $accountId)` | `StripeAccount` |

**Critical**: `getBalances()` already returns integers in cents (Stripe FC returns integers, not floats) — no `toCents()` conversion needed for balances. Values are keyed as `current`/`available`, not `ledger`/`available` like Teller.

**Critical**: `listTransactions()` uses `starting_after` cursor pagination (not `from_id`). The Stripe transaction object uses `amount` (integer, already in cents), `description`, `status`, `transacted_at`.

### Model Relationships

- `User` → `connections()` (HasMany) → `Connection` → `accounts()` (HasMany) → `Account`
- `User` → `accounts()` (HasMany) → `Account` (direct, for `syncNow()`)
- `Account.external_id` = Stripe FC account ID (e.g., `fca_xxx`)
- `Account.connection_id` = foreign key to `Connection`
- `Transaction.external_id` = Stripe FC transaction ID (replaces `teller_id`)
- `Connection.status` values: `active`, `disconnected`, `expired` (factory states confirmed)
- `Connection.stripe_account_id` — the FC account ID on the connection itself

### Current Job Structure (to be replaced)

**SyncAllAccounts** (current):
- Iterates `user->enrollments()->where('status', 'active')`
- Calls `$teller->listAccounts($accessToken)` to discover accounts
- Does `updateOrCreate` by `teller_id`
- Dispatches `SyncAccountTransactions($account, fullSync: true)`

**SyncAccountTransactions** (current):
- Loads `account->enrollment->access_token`
- Calls `syncBalances()` (maps `available`/`ledger` from Teller float strings)
- Calls `syncTransactions()` with `teller_id` cursor, paginates until <100 results
- On 401 in balances: marks `enrollment->status = 'expired'`, calls `$this->fail($e)`
- Dispatches `TransactionsSynced::dispatch($this->account)` at end

### Target Job Structure (Phase 3)

**SyncAllAccounts** (new):
- Iterate `user->connections()->where('status', 'active')`
- For each connection, iterate `connection->accounts`
- Call `$client->getAccount($account->external_id)` to refresh metadata
- Dispatch `SyncAccountTransactions($account)` per account
- Skip/log on Stripe errors per account; don't fail whole job

**SyncAccountTransactions** (new):
- No `enrollment` — loads `account->connection`
- Call `$client->getBalances($account->external_id)` — returns `['current' => int, 'available' => int, 'type' => string]`
- Map balance: `balance_current` ← `current`, `balance_available` ← `available` (already cents, no conversion)
- On auth error: mark `connection->status = 'expired'`, call `$this->fail($e)`
- Cursor: use most recent `external_id` as `starting_after` for incremental sync
- Upsert by `external_id` (not `teller_id`)
- Dispatch `TransactionsSynced::dispatch($this->account)` — must be preserved

### Event Pipeline (must be preserved)

`TransactionsSynced` listeners registered in `AppServiceProvider::boot()`:
1. `CategorizeNewTransactions` (sync listener) → dispatches `CategorizeTransactionBatch`
2. `SyncDebtsOnTransactionSync` (queued listener) → calls `DebtSynchronizer::syncAccount()`

`TransactionsCategorized` then fires `InvalidateSpendingCache`, `UpdateReadyToSpendOnTransaction`, `EmbedCategorizedTransactions`.

### CSRF Exclusion

No `VerifyCsrfToken.php` middleware class exists. The project uses Laravel 13's `bootstrap/app.php` configurator. The correct method is `$middleware->validateCsrfTokens(except: ['/stripe/webhook'])` inside the `->withMiddleware()` callback. This was confirmed by reflecting `Illuminate\Foundation\Configuration\Middleware`.

### Webhook Signature Verification

`\Stripe\Webhook::constructEvent($payload, $sigHeader, $secret, $tolerance)` is available at `vendor/stripe/stripe-php/lib/Webhook.php`. It takes:
- `$payload` — raw request body (must use `$request->getContent()`, not parsed JSON)
- `$sigHeader` — `$request->header('Stripe-Signature')`
- `$secret` — `config('stripe.webhook_secret')`

Throws `\Stripe\Exception\SignatureVerificationException` on bad signatures.
Throws `\Stripe\Exception\UnexpectedValueException` on invalid JSON.

Config key `stripe.webhook_secret` is confirmed in `config/stripe.php`.

### Test File Structure

**StripeWebhookTest** (new — HTTP controller test):
- Use `$this->postJson(route('stripe.webhook'), payload, headers)` pattern
- No auth required (public route, CSRF excluded)
- For valid signature test: need to construct payload + signature — use `\Stripe\WebhookSignature` or construct mock event with `Event::constructFrom()`
- For invalid signature: just send wrong/missing header → assert 400
- Mock `StripeFinancialConnectionsClient` via `$this->mock()` for any client calls
- Use `Queue::fake()` for asserting job dispatch
- Pattern from `FinancialConnectionsFlowTest` is closest analog

**TransactionSyncTest** (rewrite in place):
- Drop all Teller HTTP fakes, `TellerEnrollment`, `Http::fake()` patterns
- Use `Connection::factory()` + `Account::factory()` (they exist, confirmed)
- Mock `StripeFinancialConnectionsClient` singleton via `$this->mock()`
- Keep `Event::fake([TransactionsSynced::class])` + assert dispatch

### Schedule Changes

**Remove**:
- `everySixHours()` `sync-all-accounts` (iterates `enrollments`)
- `hourly()` `sync-account-transactions` (iterates `accounts` via `enrollment`)

**Add**:
- Daily fallback at 23:00: iterates `User::whereHas('connections', ...)` (not `enrollments`)

**Keep unchanged**: `monthly-budget-proposals`, `sync-debt-balances`, `generate-monthly-reports`, `analyze-spending-insights`, `snapshot-net-worth` schedules are unaffected.

### AccountList::syncNow() Changes

Current: just dispatches `SyncAllAccounts::dispatch($user)`.

New: calls `$client->refreshTransactions($account->external_id)` per account first (wrapped in try/catch for throttle), then dispatches `SyncAllAccounts::dispatch($user)`.

Auth: uses `auth('workos')->user()` (consistent with rest of Livewire components — the current code uses `auth()->user()` which should also be updated to `auth('workos')->user()` for consistency, though functionally equivalent in this app).

### Files to Delete

- `tests/Feature/TellerClientTest.php` — tests TellerClient which is being removed
- `tests/Feature/TransactionSyncTest.php` — rewritten in place (replace, not delete separately)

## Risks

- **Webhook payload must be raw bytes**: `$request->getContent()` not `$request->json()`. CSRF exclusion AND raw content capture are both required.
- **`getBalances()` returns cents already**: Unlike Teller which returned float strings requiring `toCents()`. No conversion needed.
- **`Transaction.external_id` not `teller_id`**: Confirmed — `Transaction` model uses `external_id` in fillable. The old `TransactionSyncTest` used `teller_id` which no longer exists.
- **`SyncAllAccounts` iterates connections not enrollments**: `user->connections()` vs `user->enrollments()`. `User` model has `connections()` HasMany confirmed.
- **`auth()->user()` vs `auth('workos')->user()`**: Livewire AccountList uses `auth()->user()`. Consistent pattern in app uses `auth('workos')->user()`. Low-risk discrepancy.
- **Stripe `Event` object shape in tests**: When constructing mock webhook payloads for tests, use `Stripe\Event::constructFrom(['type' => '...', 'data' => ['object' => [...]]])` pattern rather than raw arrays.
- **Connection status update on `disconnected` webhook**: Must update `Connection` by `stripe_account_id` (the FC account ID stored on Connection). Need to find the Connection by looking up `Account::where('external_id', $accountId)->first()->connection` or `Connection::where('stripe_account_id', $accountId)`.
