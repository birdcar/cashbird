# Implementation Spec: Stripe Financial Connections - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Replace the Teller Connect JS SDK frontend enrollment flow with Stripe.js `collectFinancialConnectionsAccounts`. This involves: creating a controller that generates FC sessions on the server, updating the ConnectAccount Livewire component to use Stripe.js instead of the Teller CDN script, and updating routes.

The Stripe.js flow is: server creates a `FinancialConnectionsSession` -> returns `client_secret` to frontend -> Stripe.js opens a modal for bank selection + auth -> on success, returns linked account IDs -> server creates Connection and Account records, subscribes to daily transaction/balance updates.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter=FinancialConnections`

**Playground**: Dev server (`composer run dev`) for the Livewire/JS integration, test suite for the controller logic.

**Why this approach**: This phase has both backend controller logic (testable) and frontend JS/Livewire integration (needs dev server to verify the modal flow).

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `app/Http/Controllers/FinancialConnectionsController.php` | Creates FC sessions, handles post-connection setup |
| `tests/Feature/FinancialConnectionsFlowTest.php` | Tests for the connection flow |

### Modified Files

| File Path | Changes |
|---|---|
| `app/Livewire/Accounts/ConnectAccount.php` | Remove Teller app ID, add FC session creation endpoint |
| `resources/views/livewire/accounts/connect-account.blade.php` | Replace Teller Connect JS with Stripe.js, new modal trigger logic |
| `routes/web.php` | Replace `TellerController` route with `FinancialConnectionsController` routes |
| `app/Livewire/Accounts/AccountList.php` | Update `syncNow()` to use new sync job signature (if changed) |

### Deleted Files

| File Path | Reason |
|---|---|
| `app/Http/Controllers/TellerController.php` | Replaced by FinancialConnectionsController |
| `tests/Feature/TellerEnrollmentTest.php` | Replaced by FinancialConnectionsFlowTest |

## Implementation Details

### FinancialConnectionsController

**Pattern to follow**: `app/Http/Controllers/TellerController.php`

**Overview**: Two endpoints -- one to create an FC session (returns client_secret for Stripe.js), one to handle the post-connection callback (creates Connection, syncs accounts).

```php
class FinancialConnectionsController extends Controller
{
    public function createSession(Request $request, StripeFinancialConnectionsClient $client): JsonResponse
    {
        // Create FC session with permissions: balances, transactions, ownership
        // Return client_secret as JSON
    }

    public function store(Request $request, StripeFinancialConnectionsClient $client): RedirectResponse
    {
        // Validate: stripe_account_ids (array of FC account IDs)
        // For each linked account:
        //   - Fetch account details from Stripe
        //   - Institution::firstOrCreate by external_id (Stripe institution ID)
        //   - Connection::firstOrCreate (user + institution)
        //   - Subscribe to transactions + balances
        //   - Account::updateOrCreate by external_id
        // Dispatch SyncAllAccounts for the user
        // Redirect to accounts.index with success flash
    }
}
```

**Key decisions**:
- `createSession` is a separate JSON endpoint (not part of the Livewire component) because Stripe.js needs the client_secret before opening the modal
- `store` handles multiple accounts at once (Stripe FC can return several linked accounts from one session)
- Subscription to transactions + balances happens immediately on connection so daily refresh starts right away
- Duplicate connections (same user + institution) caught by unique constraint, same as Teller

**Implementation steps**:

1. Create controller with `createSession` and `store` methods
2. `createSession`: call `$client->createSession(['balances', 'transactions', 'ownership'], route('accounts.index'))`, return `['client_secret' => $session->client_secret]`
3. `store`: validate input, loop over returned account IDs, fetch details, create records, subscribe, dispatch sync
4. Update `routes/web.php`:
   - Remove: `Route::post('/accounts/connect', [TellerController::class, 'store'])->name('teller.store')`
   - Add: `Route::post('/accounts/connect/session', [FinancialConnectionsController::class, 'createSession'])->name('connections.session')`
   - Add: `Route::post('/accounts/connect', [FinancialConnectionsController::class, 'store'])->name('connections.store')`

**Feedback loop**:
- **Playground**: Create test file with happy-path and error tests before implementing
- **Experiment**: Mock StripeFinancialConnectionsClient, test session creation returns JSON, test store creates Connection + Account + dispatches job, test duplicate rejection
- **Check command**: `php artisan test --compact --filter=FinancialConnectionsFlow`

### ConnectAccount Livewire Component

**Pattern to follow**: Current `app/Livewire/Accounts/ConnectAccount.php` and its Blade view

**Overview**: Replace Teller Connect JS SDK with Stripe.js Financial Connections modal.

**Key decisions**:
- Load Stripe.js from `https://js.stripe.com/v3/` (standard Stripe.js, not a separate FC SDK)
- The Livewire component passes the Stripe publishable key to the view via a computed property
- JavaScript flow: fetch client_secret from `/accounts/connect/session` -> call `stripe.collectFinancialConnectionsAccounts()` -> on success, POST account IDs to `/accounts/connect`
- Use Alpine.js for the button click handler and loading state (consistent with existing patterns)

**Implementation steps**:

1. Update `ConnectAccount.php`:
   - Remove `$appId` property (Teller)
   - Add computed property returning `config('stripe.publishable_key')`
2. Update `connect-account.blade.php`:
   - Remove `<script src="https://cdn.teller.io/connect/connect.js">`
   - Add `<script src="https://js.stripe.com/v3/"></script>`
   - Replace Teller Connect setup with Alpine component:
     ```javascript
     // 1. Initialize Stripe
     const stripe = Stripe(publishableKey);
     // 2. Fetch session from server
     const { client_secret } = await fetch('/accounts/connect/session', { method: 'POST', ... }).then(r => r.json());
     // 3. Open Financial Connections modal
     const { financialConnectionsSession } = await stripe.collectFinancialConnectionsAccounts({ clientSecret: client_secret });
     // 4. POST linked account IDs to store endpoint
     ```
   - Handle loading state and errors with Alpine
   - Handle user cancellation (modal closed without linking)

**Failure Modes**:

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| Session creation | Stripe API error | Invalid key, network issue | Button click fails silently | Show error toast, log server-side |
| Modal | User cancels | Closes modal without selecting accounts | No accounts linked | Handle `onExit`-equivalent, show neutral message |
| Store | Duplicate connection | User re-links same institution | DB constraint violation | Catch exception, flash info message that accounts are already connected |
| Store | Stripe account fetch fails | Account ID invalid or revoked between modal and POST | Partial connection | Wrap in transaction, rollback on any failure |

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|---|---|
| `tests/Feature/FinancialConnectionsFlowTest.php` | Full connection flow |

**Key test cases**:
- `createSession` returns JSON with client_secret
- `createSession` requires authentication
- `store` creates Institution, Connection, Account records
- `store` dispatches SyncAllAccounts job
- `store` handles multiple account IDs in one request
- `store` rejects duplicate connections (same user + institution) gracefully
- `store` requires authentication
- `store` validates required fields

### Manual Testing

- [ ] Click "Connect Bank Account" -- Stripe modal appears
- [ ] Select a test institution, authenticate, select accounts
- [ ] After success, redirected to Accounts page with success flash
- [ ] New accounts appear in the account list
- [ ] Clicking connect again for same institution shows error flash
- [ ] Canceling the modal shows no error

## Error Handling

| Error Scenario | Handling Strategy |
|---|---|
| Session creation fails | Return 500 JSON error, frontend shows toast |
| User cancels modal | No-op, user stays on connect page |
| Duplicate connection | Redirect with info flash message |
| Stripe account fetch fails during store | DB transaction rollback, redirect with error flash |
| Unauthenticated request | Redirect to login (middleware) |

## Validation Commands

```bash
# Phase-specific tests
php artisan test --compact --filter=FinancialConnectionsFlow

# Verify routes
php artisan route:list --name=connections

# Check no teller references in modified files
grep -r "teller\|Teller" app/Http/Controllers/FinancialConnectionsController.php app/Livewire/Accounts/ConnectAccount.php resources/views/livewire/accounts/connect-account.blade.php routes/web.php && echo "FAIL" || echo "PASS"

# Pint formatting
vendor/bin/pint --dirty --format agent
```
