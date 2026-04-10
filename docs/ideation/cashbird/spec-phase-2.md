# Implementation Spec: Cashbird - Phase 2: Teller & Data Layer

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Integrate Teller.io for bank account connectivity. Teller uses a JS-based enrollment widget (Teller Connect) that runs in the browser and returns an access token on successful enrollment. The backend stores tokens, syncs accounts and transactions via Teller's REST API, and handles webhooks for ongoing transaction updates.

All account types are supported: checking, savings, credit cards, loans/mortgages, and investments. Teller provides account balances and transaction history — the depth varies by institution (typically 90 days to 2 years).

Transaction data is stored in PostgreSQL with a normalized schema. A scheduled job runs daily to sync new transactions, and Teller webhooks provide near-real-time updates via `transactions.processed` events. Each user's Teller enrollments are scoped to their user ID.

The Teller API client is a dedicated service class using Laravel's HTTP client. Access tokens are encrypted at rest using Laravel's `Crypt` facade.

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=Teller`

**Playground**: Test suite with HTTP fakes for Teller API. Real API testing requires Teller sandbox credentials — configure in `.env.testing`.

**Why this approach**: Data layer heavy — models, API client, sync logic. Tests with HTTP fakes validate the integration logic without hitting Teller's API on every run.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `config/teller.php` | Teller API configuration (base URL, app ID, cert paths) |
| `app/Services/Teller/TellerClient.php` | HTTP client wrapper for Teller REST API |
| `app/Services/Teller/TellerWebhookHandler.php` | Webhook signature verification and event dispatch |
| `app/Models/Institution.php` | Bank/institution metadata |
| `app/Models/Account.php` | Financial account (checking, savings, credit, loan, investment) |
| `app/Models/Transaction.php` | Individual transaction records |
| `app/Models/TellerEnrollment.php` | Teller enrollment token storage (encrypted) |
| `app/Http/Controllers/TellerController.php` | Enrollment callback and webhook endpoint |
| `app/Livewire/Accounts/AccountList.php` | List connected accounts with balances |
| `app/Livewire/Accounts/ConnectAccount.php` | Teller Connect enrollment widget |
| `app/Livewire/Transactions/TransactionList.php` | Paginated transaction list with filters |
| `app/Jobs/SyncAccountTransactions.php` | Pull transactions for a single account |
| `app/Jobs/SyncAllAccounts.php` | Dispatch per-account sync jobs for a user |
| `app/Jobs/ProcessTellerWebhook.php` | Handle incoming Teller webhook events |
| `database/migrations/xxxx_create_institutions_table.php` | Institutions schema |
| `database/migrations/xxxx_create_teller_enrollments_table.php` | Enrollment tokens schema |
| `database/migrations/xxxx_create_accounts_table.php` | Accounts schema |
| `database/migrations/xxxx_create_transactions_table.php` | Transactions schema |
| `resources/views/livewire/accounts/account-list.blade.php` | Account list view |
| `resources/views/livewire/accounts/connect-account.blade.php` | Teller Connect widget view |
| `resources/views/livewire/transactions/transaction-list.blade.php` | Transaction list view |
| `tests/Feature/TellerClientTest.php` | API client tests with HTTP fakes |
| `tests/Feature/TellerEnrollmentTest.php` | Enrollment flow tests |
| `tests/Feature/TransactionSyncTest.php` | Transaction sync job tests |
| `tests/Feature/TellerWebhookTest.php` | Webhook handling tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `routes/web.php` | Add account routes, Teller callback, webhook endpoint |
| `app/Models/User.php` | Add `enrollments()`, `accounts()`, `transactions()` relationships |
| `resources/views/livewire/layout/sidebar.blade.php` | Activate "Accounts" nav link |
| `app/Console/Kernel.php` | Schedule daily `SyncAllAccounts` job |
| `.env.example` | Add TELLER_APP_ID, TELLER_SIGNING_SECRET, TELLER_CERT_PATH |

## Implementation Details

### Teller API Client

**Overview**: HTTP client wrapper for Teller's REST API using Laravel's Http facade. Teller uses certificate-based auth (mTLS) for API calls.

```php
// app/Services/Teller/TellerClient.php
class TellerClient
{
    public function __construct(
        private string $certPath,
        private string $keyPath,
        private string $baseUrl = 'https://api.teller.io',
    ) {}

    public function listAccounts(string $accessToken): Collection;
    public function getAccount(string $accessToken, string $accountId): array;
    public function getAccountBalances(string $accessToken, string $accountId): array;
    public function listTransactions(string $accessToken, string $accountId, ?string $fromId = null): Collection;
    public function getIdentity(string $accessToken): array;
}
```

**Key decisions**:
- mTLS: Teller requires a client certificate for API auth. Cert/key paths configured in `config/teller.php`, stored on the server filesystem (not in repo).
- Pagination: Teller uses cursor-based pagination via `from_id`. The sync job pages through all available transactions on initial sync.
- Access tokens: Encrypted with `Crypt::encryptString()` before storage in `teller_enrollments` table.

**Implementation steps**:
1. Create `config/teller.php` with app_id, base_url, cert_path, key_path, signing_secret
2. Create `TellerClient` using `Http::withOptions(['cert' => [$certPath, $keyPath]])`
3. Implement each endpoint method with proper error handling and response parsing
4. Register as singleton in `AppServiceProvider`

**Feedback loop**:
- **Playground**: Create `tests/Feature/TellerClientTest.php` with Http::fake() stubs
- **Experiment**: Test list accounts (0, 1, 5 accounts), list transactions with pagination (empty, single page, multi-page via fromId), error responses (401, 429, 500)
- **Check command**: `php artisan test --filter=TellerClient`

### Enrollment Flow

**Overview**: User connects their bank via Teller Connect (JS widget in browser). On success, the widget returns an access token that the backend stores encrypted.

**Implementation steps**:
1. Create `ConnectAccount` Livewire component that renders Teller Connect JS
2. Teller Connect config: `applicationId` from env, `onSuccess` callback posts token to backend
3. `TellerController@store` receives access token, encrypts it, creates `TellerEnrollment`
4. Dispatch `SyncAllAccounts` job for the user to pull initial account/transaction data
5. Redirect to account list on completion

**Feedback loop**:
- **Playground**: Create `tests/Feature/TellerEnrollmentTest.php`
- **Experiment**: Test enrollment creation with valid token, duplicate enrollment rejection, encrypted storage verification
- **Check command**: `php artisan test --filter=TellerEnrollment`

### Data Models

**Overview**: Normalized schema for institutions, accounts, and transactions.

```sql
-- Institutions (bank metadata)
CREATE TABLE institutions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    teller_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Teller Enrollments (encrypted access tokens)
CREATE TABLE teller_enrollments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    institution_id UUID NOT NULL REFERENCES institutions(id),
    access_token TEXT NOT NULL, -- encrypted
    status VARCHAR(50) DEFAULT 'active',
    enrolled_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, institution_id)
);

-- Accounts
CREATE TABLE accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    enrollment_id UUID NOT NULL REFERENCES teller_enrollments(id) ON DELETE CASCADE,
    teller_id VARCHAR(255) UNIQUE NOT NULL,
    institution_id UUID NOT NULL REFERENCES institutions(id),
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- checking, savings, credit_card, loan, investment
    subtype VARCHAR(50),
    currency VARCHAR(3) DEFAULT 'USD',
    balance_current BIGINT, -- cents
    balance_available BIGINT, -- cents
    balance_limit BIGINT, -- cents, for credit cards
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_accounts_user (user_id),
    INDEX idx_accounts_type (type)
);

-- Transactions
CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teller_id VARCHAR(255) UNIQUE NOT NULL,
    amount BIGINT NOT NULL, -- cents, negative = debit
    date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    merchant_name VARCHAR(255),
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    status VARCHAR(50) DEFAULT 'posted', -- pending, posted
    type VARCHAR(50), -- card_payment, transfer, deposit, etc.
    running_balance BIGINT, -- cents
    raw_data JSONB, -- full Teller response for debugging
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_transactions_user_date (user_id, date DESC),
    INDEX idx_transactions_account (account_id),
    INDEX idx_transactions_category (category_id),
    INDEX idx_transactions_status (status)
);

-- Categories (seeded, used by Phase 3 AI categorization)
CREATE TABLE categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    parent_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    icon VARCHAR(50),
    color VARCHAR(7),
    is_system BOOLEAN DEFAULT false,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(name, parent_id)
);
```

**Key decisions**:
- Money stored as BIGINT in cents — avoids floating point issues
- Transactions store `raw_data` JSONB for debugging and future field extraction
- Categories table created here but populated/used in Phase 3
- `user_id` on transactions is denormalized for query performance (avoids join through accounts)
- UUIDs throughout for non-sequential IDs

### Transaction Sync Job

**Overview**: Pulls transactions for a single account via Teller API. Handles initial bulk sync and incremental updates.

```php
// app/Jobs/SyncAccountTransactions.php
class SyncAccountTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Account $account,
        public bool $fullSync = false,
    ) {}

    public function handle(TellerClient $teller): void;
}
```

**Implementation steps**:
1. Retrieve enrollment's decrypted access token
2. Fetch account balances and update `accounts` table
3. If `fullSync`: page through all transactions from oldest to newest
4. If incremental: fetch transactions since last sync (use latest `teller_id` as cursor)
5. Upsert transactions (match on `teller_id` to handle re-fetches)
6. Update `account.last_synced_at`
7. Dispatch event `TransactionsSynced` for downstream processing (Phase 3 categorization)

**Feedback loop**:
- **Playground**: Create `tests/Feature/TransactionSyncTest.php` with Http::fake() and model factories
- **Experiment**: Test initial sync with 0 transactions, 50 transactions across 2 pages, incremental sync with 5 new transactions, handling of pending vs posted status changes
- **Check command**: `php artisan test --filter=TransactionSync`

### Webhook Handler

**Overview**: Receives Teller webhook events for real-time transaction updates.

**Implementation steps**:
1. Register `POST /webhooks/teller` route (excluded from CSRF, auth middleware)
2. Verify webhook signature using `TELLER_SIGNING_SECRET`
3. Parse event type: `transactions.processed` → dispatch `SyncAccountTransactions` for affected account
4. Return 200 immediately, process async via queued job

**Feedback loop**:
- **Playground**: Create `tests/Feature/TellerWebhookTest.php`
- **Experiment**: Test valid signature acceptance, invalid signature rejection (403), unknown event types (200 but no action), missing payload fields
- **Check command**: `php artisan test --filter=TellerWebhook`

### Livewire UI Components

**Overview**: Account list, connect widget, and transaction browser.

**Implementation steps**:
1. `AccountList`: Display all user accounts grouped by institution, show name/type/balance
2. `ConnectAccount`: Embed Teller Connect JS, handle success callback
3. `TransactionList`: Paginated list with date range filter, search by description, sort by date/amount

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/TellerClientTest.php` | API client methods with HTTP fakes |
| `tests/Feature/TellerEnrollmentTest.php` | Enrollment creation, encryption, duplicate handling |
| `tests/Feature/TransactionSyncTest.php` | Full sync, incremental sync, pagination, upsert logic |
| `tests/Feature/TellerWebhookTest.php` | Signature verification, event dispatch, error handling |

**Key test cases**:
- API client handles 401 (expired token) gracefully
- API client handles 429 (rate limit) with retry
- Enrollment stores access token encrypted (assert ciphertext != plaintext)
- Full sync pages through all transactions correctly
- Incremental sync only fetches new transactions
- Duplicate transactions (same teller_id) are upserted, not duplicated
- Webhook with invalid signature returns 403
- Webhook dispatches correct job for `transactions.processed` event
- Account balances update on sync

## Error Handling

| Error Scenario | Handling Strategy |
|---|---|
| Teller API 401 (token expired) | Mark enrollment as `expired`, notify user to re-enroll |
| Teller API 429 (rate limited) | Retry with exponential backoff (3 attempts, 1s/5s/30s) |
| Teller API 500 | Retry 3x, then fail job and log. User sees "sync failed" status on account |
| Invalid webhook signature | Return 403, log attempt. Do not process. |
| Duplicate transaction teller_id | Upsert — update existing record rather than creating duplicate |
| Enrollment with invalid institution | Log error, mark enrollment as `error`, prompt user to reconnect |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| TellerClient | Certificate not found | Cert path misconfigured or file missing | All API calls fail | Validate cert exists on app boot; clear error in health check |
| TellerClient | Stale balance data | Teller returns cached/delayed balances | "Ready to spend" inaccurate in Phase 4 | Display `last_synced_at` timestamp; allow manual refresh |
| TransactionSync | Partial sync failure | API error mid-pagination | Account has incomplete transaction history | Track sync cursor; resume from last successful page on retry |
| Enrollment | Token revoked by user at bank | User disconnects app from bank's side | Sync silently fails | Detect 401, mark enrollment expired, show reconnect prompt |
| Webhook | Delivery failure | Teller retries but server was down | Missed real-time updates | Daily scheduled sync catches up; webhook is optimization, not sole mechanism |

## Validation Commands

```bash
# Run migrations
php artisan migrate

# Run all Phase 2 tests
php artisan test --filter=Teller
php artisan test --filter=TransactionSync

# Verify routes
php artisan route:list --name=teller
php artisan route:list --name=account

# Verify models
php artisan tinker --execute="echo Account::query()->toSql();"

# Manual: Teller sandbox enrollment (requires sandbox credentials)
# php artisan serve → navigate to /accounts/connect
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
