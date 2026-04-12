# Implementation Spec: Stripe Financial Connections - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Replace the Teller polling sync engine with Stripe Financial Connections' subscription-based daily sync plus webhook-driven updates. The current system polls hourly (incremental) and every 6 hours (full). The new system uses Stripe's subscription API to automatically refresh data once daily, with webhooks notifying the app when fresh data is ready. Manual "Sync Now" triggers an on-demand refresh via the Stripe API.

Key architectural change: instead of the app initiating data pulls on a schedule, Stripe pushes notifications when data is ready. The app listens via webhooks and pulls the data on notification. The `SyncAllAccounts` job becomes a webhook-triggered pull rather than a scheduled poll. The schedule simplifies to a single daily fallback job (in case webhooks are missed) instead of hourly + 6-hour.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter=TransactionSync`

**Playground**: Test suite -- all sync logic is backend queue jobs and webhook handlers.

**Why this approach**: No UI changes in this phase. Sync jobs and webhook handlers are most efficiently validated through tests.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `app/Http/Controllers/StripeWebhookController.php` | Handles Stripe FC webhook events |
| `tests/Feature/StripeWebhookTest.php` | Tests for webhook handling |

### Modified Files

| File Path | Changes |
|---|---|
| `app/Jobs/SyncAllAccounts.php` | Rewrite to use StripeFinancialConnectionsClient instead of TellerClient |
| `app/Jobs/SyncAccountTransactions.php` | Rewrite to use Stripe FC transaction/balance APIs |
| `routes/web.php` | Add webhook route (excluded from CSRF) |
| `routes/console.php` | Replace hourly + 6-hour schedule with daily fallback + manual trigger |
| `app/Livewire/Accounts/AccountList.php` | Update `syncNow()` to trigger Stripe on-demand refresh |
| `app/Http/Middleware/VerifyCsrfToken.php` or `bootstrap/app.php` | Exclude webhook route from CSRF verification |

### Deleted Files

| File Path | Reason |
|---|---|
| `tests/Feature/TellerClientTest.php` | Teller client no longer exists |
| `tests/Feature/TransactionSyncTest.php` | Replaced by updated sync tests (rewrite in place or new file) |

## Implementation Details

### Stripe Webhook Controller

**Overview**: Receives Stripe webhook events for Financial Connections. Primary events: `financial_connections.account.refreshed_transactions_data`, `financial_connections.account.refreshed_balance`, `financial_connections.account.disconnected`.

```php
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Verify webhook signature using STRIPE_WEBHOOK_SECRET
        // Parse event type
        // Dispatch appropriate job based on event:
        //   refreshed_transactions_data -> SyncAccountTransactions
        //   refreshed_balance -> update balance on Account
        //   disconnected -> mark Connection as 'disconnected'
    }
}
```

**Key decisions**:
- Use Stripe's `Webhook::constructEvent()` for signature verification -- reject any request with invalid signature
- Dispatch jobs async (queue) for transaction sync -- webhook should return 200 quickly
- Balance updates are lightweight enough to handle inline
- `disconnected` event marks the Connection status and logs a warning

**Implementation steps**:

1. Create `StripeWebhookController` with `handle` method
2. Add route: `Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook')`
3. Exclude `/stripe/webhook` from CSRF verification
4. Verify signature, switch on event type, dispatch jobs or update records
5. Return 200 for all handled events, 400 for unverifiable signatures

**Feedback loop**:
- **Playground**: Create `StripeWebhookTest.php` before implementing
- **Experiment**: Send mock webhook payloads for each event type -- verify correct job dispatched, correct status updates, signature rejection
- **Check command**: `php artisan test --compact --filter=StripeWebhook`

### Updated SyncAllAccounts Job

**Pattern to follow**: Current `app/Jobs/SyncAllAccounts.php`

**Overview**: Triggered by webhook or daily fallback schedule. For a given user, fetches all connected FC accounts and syncs each one.

```php
class SyncAllAccounts implements ShouldQueue
{
    public function __construct(public User $user) {}

    public function handle(StripeFinancialConnectionsClient $client): void
    {
        // Get user's active connections
        // For each connection's accounts:
        //   - Fetch current account details from Stripe
        //   - Update local Account record
        //   - Dispatch SyncAccountTransactions
    }
}
```

**Key decisions**:
- No longer calls `listAccounts()` to discover accounts -- accounts are created during the connection flow (Phase 2). This job refreshes existing accounts.
- Dispatches `SyncAccountTransactions` per account, same as before.
- On Stripe API errors for a specific account, log and continue with remaining accounts (don't fail the whole job).

**Implementation steps**:

1. Rewrite `handle()` to iterate user's connections -> accounts
2. For each account, call `$client->getAccount($account->external_id)` to refresh metadata
3. Dispatch `SyncAccountTransactions` for each active account
4. Handle disconnected accounts gracefully (skip, mark connection status)

### Updated SyncAccountTransactions Job

**Pattern to follow**: Current `app/Jobs/SyncAccountTransactions.php`

**Overview**: Fetches transactions and balances for a single account from Stripe FC.

```php
class SyncAccountTransactions implements ShouldQueue
{
    public function __construct(
        public Account $account,
        public bool $fullSync = false,
    ) {}

    public function handle(StripeFinancialConnectionsClient $client): void
    {
        // 1. Fetch balances
        $balances = $client->getBalances($this->account->external_id);
        // Update account balance_current, balance_available

        // 2. Fetch transactions (paginated)
        // If incremental: use most recent external_id as cursor
        // Upsert by external_id

        // 3. Update last_synced_at
        // 4. Dispatch TransactionsSynced event
    }
}
```

**Key decisions**:
- Stripe FC `listTransactions` uses `starting_after` cursor (analogous to Teller's `from_id`)
- Balance mapping: Stripe returns `current` and `available` (for cash accounts) or `used` (for credit). Map these to existing `balance_current` and `balance_available` columns.
- Transaction mapping: Stripe FC transaction has `amount` (integer, in cents), `description`, `status` (pending/posted/void), `transacted_at`. Map to existing columns. `merchant_name` comes from `description` (Stripe FC doesn't separate it).
- Store full Stripe response in `raw_data` JSON column, same as Teller.
- **Critical**: Must still dispatch `TransactionsSynced` event after sync completes to trigger categorization, debt sync, and cache invalidation pipeline.
- On 401/permission error, mark connection as 'expired' and fail the job (same pattern as Teller).

**Failure Modes**:

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| Balance fetch | Account disconnected | User revoked bank access | Stale balance displayed | Mark connection disconnected, show "reconnect" prompt on UI |
| Transaction fetch | Rate limit from Stripe | Too many on-demand refreshes | Sync delayed | Stripe SDK retry handles this; `next_refresh_available_at` prevents over-requesting |
| Transaction upsert | Duplicate external_id | Overlapping sync windows | DB unique constraint error | Use `updateOrCreate` by `external_id` -- idempotent |
| Event dispatch | Listener throws | Categorization or debt sync fails | Transactions synced but not categorized | Listeners run in queue; individual failures don't block sync job |
| Webhook | Missed webhook | Network issues, Stripe outage | No sync triggered | Daily fallback schedule catches missed webhooks |

### Schedule Update

**Overview**: Simplify `routes/console.php` from hourly + 6-hour polling to a single daily fallback.

**Implementation steps**:

1. Remove the `everySixHours` `sync-all-accounts` schedule
2. Remove the `hourly` `sync-account-transactions` schedule
3. Add a daily fallback schedule (runs at end of day, e.g. 23:00):
   ```php
   Schedule::call(function () {
       User::whereHas('connections', fn ($q) => $q->where('status', 'active'))
           ->chunkById(100, fn ($users) => $users->each(
               fn (User $user) => SyncAllAccounts::dispatch($user)
           ));
   })->dailyAt('23:00')->name('sync-all-accounts-fallback')->withoutOverlapping();
   ```
4. This is a safety net -- primary sync is webhook-driven

### Manual Sync (Sync Now)

**Pattern to follow**: Current `AccountList::syncNow()`

**Overview**: Update the "Sync Now" button to trigger Stripe on-demand refresh.

**Implementation steps**:

1. Update `AccountList::syncNow()`:
   ```php
   public function syncNow(): void
   {
       $client = app(StripeFinancialConnectionsClient::class);
       $user = auth('workos')->user();

       foreach ($user->accounts as $account) {
           try {
               $client->refreshTransactions($account->external_id);
           } catch (\Exception $e) {
               // Account may not be eligible for refresh yet (next_refresh_available_at)
               // Log and continue
           }
       }

       // Dispatch full sync to pull whatever data is now available
       SyncAllAccounts::dispatch($user);

       session()->flash('message', 'Sync requested. Transactions will update shortly.');
   }
   ```
2. The refresh request tells Stripe to fetch fresh data from the bank. Actual data arrives via webhook when ready, or the dispatched `SyncAllAccounts` picks up whatever is currently available.
3. Handle `next_refresh_available_at` throttle gracefully -- if the account was recently refreshed, Stripe may reject the request. Catch and continue.

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|---|---|
| `tests/Feature/StripeWebhookTest.php` | Webhook signature verification, event routing, job dispatch |
| `tests/Feature/TransactionSyncTest.php` | Rewritten for Stripe FC -- sync jobs, balance updates, pagination |

**Key test cases**:
- Webhook: valid signature dispatches correct job
- Webhook: invalid signature returns 400
- Webhook: `refreshed_transactions_data` event dispatches SyncAccountTransactions
- Webhook: `disconnected` event marks connection as disconnected
- SyncAccountTransactions: creates new transactions from Stripe FC data
- SyncAccountTransactions: upserts existing transactions (status change)
- SyncAccountTransactions: updates balances
- SyncAccountTransactions: dispatches TransactionsSynced event
- SyncAccountTransactions: handles empty transaction list
- SyncAccountTransactions: marks connection expired on auth error
- SyncAllAccounts: dispatches per-account sync jobs
- Manual sync: triggers refresh + dispatches sync

## Error Handling

| Error Scenario | Handling Strategy |
|---|---|
| Invalid webhook signature | Return 400, do not process |
| Account disconnected (webhook) | Mark connection 'disconnected', stop syncing that account |
| Stripe auth error during sync | Mark connection 'expired', fail job |
| Refresh throttled (next_refresh_available_at) | Catch exception, log info, continue with other accounts |
| Webhook delivery failure (Stripe side) | Daily fallback schedule catches missed syncs |

## Validation Commands

```bash
# Sync tests
php artisan test --compact --filter=TransactionSync
php artisan test --compact --filter=StripeWebhook

# Verify webhook route exists
php artisan route:list --name=stripe.webhook

# Verify schedule
php artisan schedule:list

# Check no teller references in modified files
grep -r "teller\|Teller" app/Jobs/ routes/console.php && echo "FAIL" || echo "PASS"

# Pint formatting
vendor/bin/pint --dirty --format agent
```
