# Implementation Spec: Cashbird - Phase 3: AI Categorization Engine

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Build an AI-powered transaction categorization system using Laravel 13's AI SDK. The core is a `CategorizationAgent` that classifies transactions into a hierarchical category tree (e.g., "Food & Drink > Restaurants > Fast Food"). The agent uses merchant name, transaction description, amount, and historical patterns as context.

Categorization runs in two modes:
1. **Batch**: On initial sync (Phase 2), categorize all historical transactions in chunked jobs
2. **Realtime**: On new transaction via `TransactionsSynced` event listener, categorize immediately

Users can override any AI-assigned category. Overrides are stored and used as few-shot examples for future categorization of similar merchants, creating a learning loop.

Historical spending aggregation is computed and cached — category breakdowns by month/quarter/year. This data feeds the budget engine (Phase 4) and reports (Phase 6).

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=Categorization`

**Playground**: Test suite with AI SDK fakes. The AI agent is tested by mocking the AI SDK's response layer, letting us verify prompt construction and response parsing without API calls.

**Why this approach**: Core logic is prompt engineering + data pipeline. Tests validate the categorization pipeline, override learning, and aggregation math.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `app/Agents/CategorizationAgent.php` | Laravel AI SDK agent for transaction classification |
| `app/Services/Categorization/CategoryResolver.php` | Resolves AI output to category models, handles overrides |
| `app/Services/Categorization/SpendingAggregator.php` | Computes spending breakdowns by category/period |
| `app/Jobs/CategorizeTransactionBatch.php` | Batch categorization for historical transactions |
| `app/Listeners/CategorizeNewTransactions.php` | Event listener for TransactionsSynced |
| `app/Events/TransactionsCategorized.php` | Fired after categorization completes |
| `app/Livewire/Transactions/CategoryOverride.php` | UI for overriding transaction category |
| `app/Livewire/Dashboard/SpendingBreakdown.php` | Category spending chart component |
| `database/migrations/xxxx_create_category_overrides_table.php` | User overrides schema |
| `database/migrations/xxxx_create_spending_aggregations_table.php` | Cached aggregation data |
| `database/seeders/CategorySeeder.php` | Seed default category hierarchy |
| `resources/views/livewire/transactions/category-override.blade.php` | Category override UI |
| `resources/views/livewire/dashboard/spending-breakdown.blade.php` | Spending chart view |
| `tests/Feature/CategorizationAgentTest.php` | AI agent categorization tests |
| `tests/Feature/CategoryResolverTest.php` | Override learning and resolution tests |
| `tests/Feature/SpendingAggregatorTest.php` | Aggregation computation tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `app/Models/Transaction.php` | Add `category()` relationship, `categorized_at` field |
| `app/Models/Category.php` | Add `children()`, `parent()` relationships, `fullPath()` accessor |
| `app/Providers/EventServiceProvider.php` | Register `TransactionsSynced` → `CategorizeNewTransactions` |
| `resources/views/livewire/transactions/transaction-list.blade.php` | Add category column, filter by category |
| `resources/views/livewire/dashboard.blade.php` | Add SpendingBreakdown component |

## Implementation Details

### Category Hierarchy

**Overview**: Seed a two-level category tree covering common personal finance categories.

**Default categories** (top-level → children):
- Income: Salary, Freelance, Interest, Dividends, Refunds, Other Income
- Housing: Rent/Mortgage, Utilities, Insurance, Maintenance, Property Tax
- Transportation: Gas, Public Transit, Ride Share, Parking, Car Payment, Car Insurance
- Food & Drink: Groceries, Restaurants, Fast Food, Coffee, Delivery, Alcohol
- Shopping: Amazon, Clothing, Electronics, Home Goods, Gifts
- Entertainment: Streaming, Gaming, Events, Hobbies
- Health: Insurance Premium, Doctor, Pharmacy, Fitness
- Personal: Haircut, Subscriptions, Phone, Internet
- Debt Payments: Credit Card, Payday Loan, Student Loan, Personal Loan
- Savings & Investments: Transfer to Savings, Investment Contribution, 401k
- Fees & Charges: Bank Fee, ATM Fee, Overdraft, Late Fee, Interest Charge
- Transfers: Internal Transfer, Peer Payment (Venmo/Zelle/CashApp)
- Uncategorized: (default for unmatched)

**Implementation steps**:
1. Create `CategorySeeder` with hierarchical insert
2. Run seeder in migration or via `php artisan db:seed --class=CategorySeeder`

### Categorization Agent

**Overview**: Laravel AI SDK agent that classifies transactions using merchant name, description, and amount.

```php
// app/Agents/CategorizationAgent.php
use Laravel\AI\Agent;

class CategorizationAgent extends Agent
{
    protected string $model = 'claude-sonnet-4-5-20250514';

    protected string $instructions = <<<'PROMPT'
    You are a financial transaction categorizer. Given a transaction, classify it
    into the most specific category from the provided category tree.

    Rules:
    - Use merchant_name as primary signal, description as secondary
    - If a user override exists for this merchant, always use that category
    - For ambiguous transactions, prefer the most common category for that merchant
    - Return the full category path: "Parent > Child"
    - If truly unrecognizable, return "Uncategorized"
    PROMPT;

    protected array $tools = [];

    public function categorize(Transaction $transaction, array $overrides = []): string;
}
```

**Key decisions**:
- Use Sonnet for cost efficiency — categorization is a high-volume, relatively simple task
- Batch transactions in groups of 20 per API call to reduce round-trips
- Include user overrides as few-shot examples in the prompt for learning
- Cache merchant → category mappings after 3 consistent categorizations of the same merchant

**Implementation steps**:
1. Create `CategorizationAgent` extending Laravel AI Agent
2. Build prompt template with category tree, transaction data, and override examples
3. Implement `categorize()` for single transaction and `categorizeBatch()` for bulk
4. Parse response into category path, resolve to `Category` model via `CategoryResolver`

**Feedback loop**:
- **Playground**: Create `tests/Feature/CategorizationAgentTest.php` with AI SDK fakes
- **Experiment**: Test with known merchants (WALMART → Groceries, NETFLIX → Streaming), ambiguous descriptions ("PAYMENT THANK YOU" → context-dependent), override learning (user says COSTCO is Groceries not Shopping)
- **Check command**: `php artisan test --filter=CategorizationAgent`

### Category Resolver & Override Learning

**Overview**: Resolves AI output to category models and manages the override learning loop.

```php
// app/Services/Categorization/CategoryResolver.php
class CategoryResolver
{
    public function resolve(string $categoryPath): ?Category;
    public function getOverridesForMerchant(string $merchantName, int $userId): ?Category;
    public function saveOverride(Transaction $transaction, Category $category, int $userId): void;
    public function getMerchantCache(): Collection; // merchant_name → category_id after 3+ consistent
}
```

**Data model for overrides**:
```sql
CREATE TABLE category_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    merchant_name VARCHAR(255) NOT NULL,
    category_id UUID NOT NULL REFERENCES categories(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, merchant_name)
);
```

**Implementation steps**:
1. `resolve()`: Parse "Parent > Child" path, find matching Category
2. `getOverridesForMerchant()`: Check overrides table for user's merchant preference
3. `saveOverride()`: Upsert into overrides table, invalidate merchant cache
4. Build merchant cache: after 3 consistent AI categorizations for the same merchant (no overrides), cache the mapping to skip AI for that merchant

**Feedback loop**:
- **Playground**: Create `tests/Feature/CategoryResolverTest.php`
- **Experiment**: Test path resolution ("Food & Drink > Groceries" → correct Category model), override saves and retrieval, cache population after 3 consistent categorizations, override takes priority over AI
- **Check command**: `php artisan test --filter=CategoryResolver`

### Spending Aggregator

**Overview**: Computes and caches spending breakdowns by category and time period.

```php
// app/Services/Categorization/SpendingAggregator.php
class SpendingAggregator
{
    public function forPeriod(int $userId, string $period, Carbon $start, Carbon $end): array;
    public function topCategories(int $userId, Carbon $start, Carbon $end, int $limit = 10): Collection;
    public function monthOverMonth(int $userId, int $months = 6): array;
    public function invalidateCache(int $userId, Carbon $month): void;
}
```

**Aggregation storage**:
```sql
CREATE TABLE spending_aggregations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id UUID REFERENCES categories(id),
    period_type VARCHAR(20) NOT NULL, -- monthly, quarterly, yearly
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_amount BIGINT NOT NULL, -- cents
    transaction_count INTEGER NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, category_id, period_type, period_start)
);
```

**Key decisions**:
- Aggregations are materialized (not computed on-the-fly) for dashboard performance
- Invalidated when new transactions are categorized or categories change
- Null `category_id` aggregation = total spending for that period

**Implementation steps**:
1. `forPeriod()`: Query aggregation table or compute from transactions if cache miss
2. `topCategories()`: Ranked list by amount for a date range
3. `monthOverMonth()`: Array of per-month totals for trend display
4. Wire `TransactionsCategorized` event to invalidate affected month's cache

**Feedback loop**:
- **Playground**: Create `tests/Feature/SpendingAggregatorTest.php` with transaction factories
- **Experiment**: Test with empty transactions (zeros), single month with known amounts (verify math), cache invalidation after new categorization, month-over-month with gaps (missing months should be zero)
- **Check command**: `php artisan test --filter=SpendingAggregator`

### Batch Categorization Job

**Overview**: Processes uncategorized transactions in batches of 20.

**Implementation steps**:
1. Query transactions where `category_id IS NULL AND categorized_at IS NULL`
2. Check merchant cache first — skip AI for cached merchants
3. Check user overrides — skip AI for overridden merchants
4. Batch remaining transactions (up to 20) into single AI call
5. Apply results via `CategoryResolver::resolve()`
6. Mark transactions as categorized (`categorized_at = now()`)
7. Dispatch `TransactionsCategorized` event to trigger aggregation invalidation

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/CategorizationAgentTest.php` | AI prompt construction, response parsing, batch mode |
| `tests/Feature/CategoryResolverTest.php` | Path resolution, overrides, merchant cache |
| `tests/Feature/SpendingAggregatorTest.php` | Period aggregation, top categories, cache invalidation |

**Key test cases**:
- Known merchants categorize correctly (mock AI response)
- User override takes priority over AI categorization
- Merchant cache populates after 3 consistent categorizations
- Batch job processes exactly 20 at a time
- SpendingAggregator computes correct totals from transaction data
- Cache invalidation triggers on new categorization
- Uncategorized transactions get processed by event listener
- Category hierarchy resolves full paths correctly

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| CategorizationAgent | AI API timeout | Provider outage or slow response | Transactions stay uncategorized | Retry 3x; leave uncategorized with `categorized_at = null` for next batch run |
| CategorizationAgent | Hallucinated category | AI returns category not in tree | Resolution fails, transaction uncategorized | `CategoryResolver::resolve()` returns null for unknown paths; fall back to "Uncategorized" |
| CategoryResolver | Override conflict | User overrides same merchant differently on two devices | Last-write-wins via UNIQUE constraint | Acceptable — user will re-override if needed |
| SpendingAggregator | Stale cache | Aggregation not invalidated after re-categorization | Dashboard shows wrong numbers | Invalidation on `TransactionsCategorized` event; add cache TTL as safety net |
| BatchJob | Duplicate categorization | Job runs twice (queue retry) | Wasted AI calls but no data corruption | Idempotent: check `categorized_at` before processing |

## Validation Commands

```bash
# Seed categories
php artisan db:seed --class=CategorySeeder

# Run all Phase 3 tests
php artisan test --filter=Categorization
php artisan test --filter=CategoryResolver
php artisan test --filter=SpendingAggregator

# Verify categories seeded
php artisan tinker --execute="echo Category::count();"

# Manual: trigger categorization
# php artisan tinker --execute="dispatch(new CategorizeTransactionBatch(User::first()));"
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
