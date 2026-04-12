# Implementation Spec: Stripe Financial Connections - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Install the Stripe PHP SDK, create a config file, build the `StripeFinancialConnectionsClient` service, and migrate the database schema from Teller-specific naming to provider-agnostic naming. This phase establishes all the infrastructure that Phases 2 and 3 depend on.

The client service wraps Stripe's PHP SDK for Financial Connections operations: creating sessions, listing accounts, fetching balances, subscribing to transaction updates, triggering refreshes, and listing transactions. It follows the same singleton pattern as the existing `TellerClient` but uses the Stripe SDK directly instead of raw HTTP.

Schema changes are destructive (fresh start): drop `teller_enrollments`, rename `teller_id` columns to `external_id`, create new `connections` table.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact --filter=StripeFinancialConnections`

**Playground**: Test suite -- create test files first, then implement against them.

**Why this approach**: This phase is entirely backend infrastructure with no UI. Tests are the fastest validation.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `config/stripe.php` | Stripe configuration (keys, webhook secret) |
| `app/Services/Stripe/StripeFinancialConnectionsClient.php` | FC API wrapper service |
| `app/Models/Connection.php` | Replaces TellerEnrollment model |
| `database/migrations/xxxx_replace_teller_with_stripe_fc.php` | Single migration: drop teller_enrollments, create connections, rename teller_id columns |
| `database/factories/ConnectionFactory.php` | Factory for Connection model |
| `tests/Feature/StripeFinancialConnectionsClientTest.php` | Tests for the FC client |
| `tests/Feature/ConnectionTest.php` | Tests for the Connection model |

### Modified Files

| File Path | Changes |
|---|---|
| `app/Models/Account.php` | Change `teller_id` references to `external_id`, update `enrollment` relationship to `connection` |
| `app/Models/Transaction.php` | Change `teller_id` references to `external_id` |
| `app/Models/Institution.php` | Change `teller_id` references to `external_id` |
| `app/Models/User.php` | Change `enrollments()` relationship to `connections()`, update type hint |
| `app/Providers/AppServiceProvider.php` | Replace TellerClient singleton with StripeFinancialConnectionsClient |
| `.env.example` | Replace Teller env vars with Stripe env vars |

### Deleted Files

| File Path | Reason |
|---|---|
| `app/Services/Teller/TellerClient.php` | Replaced by StripeFinancialConnectionsClient |
| `config/teller.php` | Replaced by config/stripe.php |
| `app/Models/TellerEnrollment.php` | Replaced by Connection model |
| `database/factories/TellerEnrollmentFactory.php` | Replaced by ConnectionFactory |

## Implementation Details

### Stripe SDK Installation & Config

**Overview**: Install the Stripe PHP SDK and create configuration.

**Implementation steps**:

1. Run `composer require stripe/stripe-php`
2. Create `config/stripe.php` with keys:
   - `secret_key` => `env('STRIPE_SECRET_KEY')`
   - `publishable_key` => `env('STRIPE_PUBLISHABLE_KEY')`
   - `webhook_secret` => `env('STRIPE_WEBHOOK_SECRET')`
3. Update `.env.example`: remove `TELLER_APP_ID`, `TELLER_BASE_URL`, `TELLER_CERT_PATH`, `TELLER_KEY_PATH`; add `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`

### Connection Model

**Pattern to follow**: `app/Models/TellerEnrollment.php`

**Overview**: Provider-agnostic replacement for TellerEnrollment. Stores the Stripe FC account link reference.

```php
class Connection extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'institution_id',
        'stripe_account_id',    // Stripe FC linked account ID (fa_xxx)
        'status',               // active, disconnected, expired
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
        ];
    }

    // Relationships: user(), institution(), accounts()
}
```

**Key decisions**:
- `stripe_account_id` stores the Stripe Financial Connections account ID, not a generic token. Stripe FC doesn't use bearer tokens per-request like Teller -- the server-side SDK authenticates with the secret key, and operations reference the FC account ID.
- No encrypted access_token column needed -- Stripe SDK handles auth via the secret key in config.
- Keep `institution_id` FK to preserve institution relationship.

**Implementation steps**:
1. Create `Connection` model with UUIDs, relationships, and fillable fields
2. Create `ConnectionFactory` based on TellerEnrollmentFactory pattern
3. Write `ConnectionTest` covering: creation, relationships, status transitions

### Database Migration

**Overview**: Single migration that performs the Teller-to-Stripe schema transition.

**Implementation steps**:

1. Drop `teller_enrollments` table
2. Create `connections` table:
   - `id` UUID PK
   - `user_id` FK -> users (cascade)
   - `institution_id` FK UUID -> institutions (cascade)
   - `stripe_account_id` string, unique
   - `status` string(50), default 'active'
   - `connected_at` timestamp
   - `timestamps`
   - Unique constraint on (user_id, institution_id)
3. Rename columns:
   - `institutions.teller_id` -> `institutions.external_id`
   - `accounts.teller_id` -> `accounts.external_id`
   - `accounts.enrollment_id` -> `accounts.connection_id`
   - `transactions.teller_id` -> `transactions.external_id`
4. Drop `accounts.enrollment_id` FK, add `accounts.connection_id` FK -> connections

**Failure Modes**:

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| Migration | Data loss on rollback | Running `migrate:rollback` after dropping teller_enrollments | Cannot restore Teller data | This is intentional -- fresh start. Migration down() should create the connections table structure, not restore Teller |
| Migration | FK constraint violation | Running migration with existing account data referencing teller_enrollments | Migration fails | Migration must drop FK constraints before dropping tables, recreate with new references |

### StripeFinancialConnectionsClient

**Pattern to follow**: `app/Services/Teller/TellerClient.php`

**Overview**: Wraps the Stripe PHP SDK for Financial Connections operations. Registered as a singleton.

```php
class StripeFinancialConnectionsClient
{
    private StripeClient $stripe;

    public function __construct(string $secretKey)
    {
        $this->stripe = new StripeClient($secretKey);
    }

    public function createSession(array $permissions, string $returnUrl): Session
    public function getAccount(string $accountId): FinancialConnectionsAccount
    public function listAccountsBySession(string $sessionId): Collection
    public function getBalances(string $accountId): array
    public function subscribeToTransactions(string $accountId): void
    public function subscribeToBalances(string $accountId): void
    public function refreshTransactions(string $accountId): TransactionRefresh
    public function listTransactions(string $accountId, ?string $startingAfter = null): Collection
    public function disconnect(string $accountId): void
}
```

**Key decisions**:
- Uses `StripeClient` instance (not static `Stripe::setApiKey()`) for testability
- `createSession` accepts permissions array (`['balances', 'transactions', 'ownership']`) and returns a session with `client_secret` for the frontend
- `subscribeToTransactions/Balances` calls the Stripe subscription API to enable daily auto-refresh
- `listTransactions` uses cursor-based pagination with `starting_after` (Stripe's standard pattern, analogous to Teller's `from_id`)
- `refreshTransactions` triggers an on-demand refresh; returns a refresh object with status

**Implementation steps**:

1. Create `StripeFinancialConnectionsClient` with constructor accepting secret key
2. Implement each method wrapping the corresponding Stripe SDK call
3. Register as singleton in `AppServiceProvider`:
   ```php
   $this->app->singleton(StripeFinancialConnectionsClient::class, fn () =>
       new StripeFinancialConnectionsClient(config('stripe.secret_key'))
   );
   ```
4. Remove TellerClient singleton registration

**Feedback loop**:
- **Playground**: Create `StripeFinancialConnectionsClientTest.php` with describe block before implementing
- **Experiment**: Mock Stripe SDK responses for each method -- test happy path, empty responses, error responses (auth failure, rate limit, disconnected account)
- **Check command**: `php artisan test --compact --filter=StripeFinancialConnectionsClient`

### Model Updates

**Overview**: Update Account, Transaction, Institution, and User models for new naming.

**Implementation steps**:

1. `Account.php`: rename `teller_id` -> `external_id` in fillable/casts, rename `enrollment()` relationship to `connection()` pointing at Connection model, update FK reference
2. `Transaction.php`: rename `teller_id` -> `external_id` in fillable
3. `Institution.php`: rename `teller_id` -> `external_id` in fillable and any methods
4. `User.php`: rename `enrollments()` to `connections()`, change return type to Connection
5. Update `AccountFactory`, `TransactionFactory`, `InstitutionFactory` -- change `teller_id` references to `external_id`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|---|---|
| `tests/Feature/StripeFinancialConnectionsClientTest.php` | All client methods with mocked Stripe SDK |
| `tests/Feature/ConnectionTest.php` | Connection model CRUD and relationships |

**Key test cases**:
- Client: createSession returns session with client_secret
- Client: listTransactions paginates correctly with starting_after cursor
- Client: getBalances returns current/available amounts
- Client: subscribeToTransactions succeeds
- Client: refreshTransactions returns refresh status
- Client: disconnect marks account disconnected
- Client: handles Stripe API errors (invalid key, rate limit, not found)
- Connection: encrypted fields if any, user/institution relationships
- Connection: unique constraint on (user_id, institution_id) prevents duplicates

## Error Handling

| Error Scenario | Handling Strategy |
|---|---|
| Invalid Stripe API key | Throw config exception at boot, not at request time |
| Stripe rate limit (429) | Stripe SDK has built-in retry; configure `max_network_retries` |
| FC account disconnected | Mark connection status as 'disconnected', skip in sync |

## Validation Commands

```bash
# Run phase-specific tests
php artisan test --compact --filter=StripeFinancialConnections
php artisan test --compact --filter=ConnectionTest

# Verify migration runs
php artisan migrate --pretend

# Check no teller references remain in new files
grep -r "teller" app/Services/Stripe/ app/Models/Connection.php config/stripe.php && echo "FAIL: teller references found" || echo "PASS"

# Pint formatting
vendor/bin/pint --dirty --format agent
```

## Open Items

- [ ] Confirm exact Stripe FC permissions needed: `balances`, `transactions`, `ownership` -- or just `balances` + `transactions`?
- [ ] Stripe SDK version -- use latest stable (`^16.x` as of 2026)
