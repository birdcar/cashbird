# Implementation Spec: Cashbird - Phase 6: AI Insights, Reports & MCP Server

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Build three AI-powered capabilities and expose them via both a web UI and an MCP server:

1. **Monthly Report Agent** — Generates narrative spending reports summarizing trends, anomalies, and budget adherence. Scheduled monthly, stored for historical access.

2. **Insights Agent** — Continuously analyzes spending patterns to surface actionable insights: unused subscriptions, spending anomalies, savings opportunities, debt payoff milestones.

3. **Natural Language Query Agent** — Answers arbitrary financial questions from transaction data ("How much did I spend on dining in March?" / "What's my biggest expense category this year?"). Available via MCP server and in-app chat.

4. **MCP Server** — Exposes Cashbird's data and agents as MCP tools via `laravel/mcp`, making the app queryable from Claude Code, Claude Desktop, or any MCP-compatible client.

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=Insights`

**Playground**: Test suite for agent logic + `php artisan mcp:serve` for MCP tool testing via Claude Code.

**Why this approach**: Agent logic is testable with mocked AI responses. MCP tools are testable via direct invocation and Claude Code integration testing.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `app/Agents/ReportAgent.php` | Monthly narrative report generation |
| `app/Agents/InsightsAgent.php` | Spending pattern analysis and insight surfacing |
| `app/Agents/QueryAgent.php` | Natural language financial query answering |
| `app/Models/Report.php` | Stored monthly reports |
| `app/Models/Insight.php` | Surfaced insights with read/dismiss status |
| `app/Services/AI/QueryExecutor.php` | Translates NLQ into database queries and formats results |
| `app/Jobs/GenerateMonthlyReport.php` | Scheduled monthly report generation |
| `app/Jobs/AnalyzeSpendingInsights.php` | Weekly insight analysis job |
| `app/MCP/Tools/QueryTransactionsTool.php` | MCP tool: search/filter transactions |
| `app/MCP/Tools/GetBudgetTool.php` | MCP tool: current budget status |
| `app/MCP/Tools/GetDebtStatusTool.php` | MCP tool: debt overview and projections |
| `app/MCP/Tools/AskFinancialQuestionTool.php` | MCP tool: natural language query |
| `app/MCP/Tools/GetReportTool.php` | MCP tool: retrieve monthly report |
| `app/MCP/Tools/GetInsightsTool.php` | MCP tool: list active insights |
| `app/MCP/CashbirdMcpServer.php` | MCP server registration and configuration |
| `app/Livewire/Reports/ReportList.php` | List of monthly reports |
| `app/Livewire/Reports/ReportView.php` | Single report display (markdown rendered) |
| `app/Livewire/Insights/InsightsFeed.php` | Insights feed with dismiss/act actions |
| `app/Livewire/Chat/FinancialChat.php` | In-app NLQ chat interface |
| `database/migrations/xxxx_create_reports_table.php` | Reports schema |
| `database/migrations/xxxx_create_insights_table.php` | Insights schema |
| `resources/views/livewire/reports/` | Report views |
| `resources/views/livewire/insights/` | Insight views |
| `resources/views/livewire/chat/` | Chat views |
| `tests/Feature/ReportAgentTest.php` | Report generation tests |
| `tests/Feature/InsightsAgentTest.php` | Insight analysis tests |
| `tests/Feature/QueryAgentTest.php` | NLQ tests |
| `tests/Feature/McpToolsTest.php` | MCP tool invocation tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `routes/web.php` | Add report, insights, and chat routes |
| `resources/views/livewire/layout/sidebar.blade.php` | Activate "Reports" nav link, add "Insights" badge |
| `resources/views/livewire/dashboard.blade.php` | Add insights card and recent report link |
| `app/Console/Kernel.php` | Schedule monthly report (1st of month) and weekly insights |
| `app/Providers/AppServiceProvider.php` | Register MCP server |

## Implementation Details

### Data Model

```sql
CREATE TABLE reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    period_month DATE NOT NULL, -- first day of reported month
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL, -- markdown narrative
    summary TEXT, -- 2-3 sentence executive summary
    data JSONB NOT NULL, -- structured data backing the report
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, period_month)
);

CREATE TABLE insights (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- unused_subscription, spending_spike, savings_opportunity, debt_milestone, anomaly
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    data JSONB, -- supporting data (amounts, dates, merchant names)
    severity VARCHAR(20) DEFAULT 'info', -- info, warning, action_required
    status VARCHAR(20) DEFAULT 'active', -- active, dismissed, acted_on
    dismissed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_insights_user_status (user_id, status)
);
```

### Monthly Report Agent

**Overview**: Generates narrative monthly spending reports with structured sections.

```php
class ReportAgent extends Agent
{
    protected string $model = 'claude-sonnet-4-5-20250514';

    protected string $instructions = <<<'PROMPT'
    Generate a monthly financial report in markdown. Structure:

    ## Monthly Summary
    One paragraph: total income, total spending, net savings/deficit, % of income saved.

    ## Category Breakdown
    Top 10 categories by spending. For each: amount, % of total, vs last month (↑↓), vs budget allocation.

    ## Notable Changes
    Significant month-over-month changes (>20% swing in any category). Explain likely causes.

    ## Budget Adherence
    Categories over/under budget. Overall budget score (% of categories within allocation).

    ## Debt Progress
    Balance changes, payments made, projected payoff date updates.

    ## Recommendations
    2-3 specific, actionable suggestions based on this month's data.

    Use plain language. Be direct about problems. Reference specific amounts.
    PROMPT;
}
```

**Implementation steps**:
1. Create agent with spending data context (aggregations from Phase 3, budget from Phase 4, debt from Phase 5)
2. `GenerateMonthlyReport` job gathers all data for previous month
3. Agent generates markdown report
4. Store in `reports` table with both markdown and structured data backing
5. Notify user that report is ready

**Feedback loop**:
- **Playground**: Create `tests/Feature/ReportAgentTest.php` with AI SDK fakes
- **Experiment**: Test with complete month data (verify all sections present), test with no debt (debt section omitted), test with no budget (budget section shows "no budget set"), test with 0 transactions (graceful empty state)
- **Check command**: `php artisan test --filter=ReportAgent`

### Insights Agent

**Overview**: Analyzes spending patterns to surface actionable insights.

**Insight types**:
1. **Unused subscription**: Recurring charge from a service with no recent usage signal (same merchant, no other transaction types)
2. **Spending spike**: Category spending >50% above 3-month average
3. **Savings opportunity**: Category consistently under budget (reallocation potential)
4. **Debt milestone**: Approaching payoff (<$500 remaining or <3 months to payoff)
5. **Anomaly**: Unusual transaction (amount >3 std dev from merchant average)

```php
class InsightsAgent extends Agent
{
    protected string $model = 'claude-sonnet-4-5-20250514';

    protected string $instructions = <<<'PROMPT'
    Analyze the user's financial data and identify actionable insights.
    Each insight should be:
    - Specific (name the merchant/category/amount)
    - Actionable (what should the user do?)
    - Quantified (how much money is involved?)

    Return as JSON array: [{type, title, description, severity, data}]
    PROMPT;

    protected array $tools = [
        // Agent can call these to gather context:
        'getSubscriptions',    // list recurring charges
        'getSpendingTrends',   // month-over-month by category
        'getDebtStatus',       // current debt balances
        'getBudgetAdherence',  // over/under per category
    ];
}
```

**Implementation steps**:
1. Define tools that the InsightsAgent can call to query local data
2. Weekly job gathers context and runs agent
3. Agent uses tools to explore data and identify patterns
4. Parse output into `Insight` records
5. Deduplicate: don't re-surface dismissed insights for same data point

**Feedback loop**:
- **Playground**: Create `tests/Feature/InsightsAgentTest.php`
- **Experiment**: Test unused subscription detection (Netflix charge but no usage), spending spike (dining 80% above average), debt milestone ($200 remaining on a card)
- **Check command**: `php artisan test --filter=InsightsAgent`

### Natural Language Query Agent

**Overview**: Answers financial questions by querying the database and formatting results.

```php
class QueryAgent extends Agent
{
    protected string $model = 'claude-sonnet-4-5-20250514';

    protected string $instructions = <<<'PROMPT'
    You are a financial data assistant. Answer the user's question about their
    finances by querying their transaction, budget, and debt data.

    Always include specific numbers. Format currency as $X,XXX.XX.
    If the question is ambiguous, state your interpretation before answering.
    If data is insufficient, say so clearly.
    PROMPT;

    protected array $tools = [
        'queryTransactions',  // search with filters (date range, category, merchant, amount range)
        'getSpendingSummary', // aggregated by category for a period
        'getBudgetStatus',    // current period allocations vs actuals
        'getDebtOverview',    // all debts with balances and projections
        'getReadyToSpend',    // current ready-to-spend per category
    ];
}
```

**Implementation steps**:
1. Define tools as PHP callables that query Eloquent models
2. `queryTransactions` tool: accepts date_start, date_end, category, merchant, min_amount, max_amount
3. `getSpendingSummary` tool: accepts period (month/quarter/year) and returns category totals
4. Wire up in `FinancialChat` Livewire component with streaming response
5. Also exposed via MCP `AskFinancialQuestionTool`

**Feedback loop**:
- **Playground**: Create `tests/Feature/QueryAgentTest.php` with AI SDK fakes and seeded transactions
- **Experiment**: "How much did I spend on dining in March?" (should query transactions filtered by category + date range), "What's my biggest expense?" (should query spending summary), "Am I on budget?" (should query budget status)
- **Check command**: `php artisan test --filter=QueryAgent`

### MCP Server

**Overview**: Expose Cashbird as an MCP server via `laravel/mcp` package for AI client integration.

```php
// app/MCP/CashbirdMcpServer.php
use Laravel\MCP\Facades\MCP;

class CashbirdMcpServer
{
    public function register(): void
    {
        MCP::tool('query_transactions', QueryTransactionsTool::class)
            ->description('Search and filter financial transactions');

        MCP::tool('get_budget', GetBudgetTool::class)
            ->description('Get current budget status with allocations and spending');

        MCP::tool('get_debt_status', GetDebtStatusTool::class)
            ->description('Get debt overview with balances and payoff projections');

        MCP::tool('ask_financial_question', AskFinancialQuestionTool::class)
            ->description('Ask a natural language question about finances');

        MCP::tool('get_report', GetReportTool::class)
            ->description('Retrieve a monthly financial report');

        MCP::tool('get_insights', GetInsightsTool::class)
            ->description('List active financial insights and recommendations');
    }
}
```

**MCP tool specifications**:

| Tool | Input | Output |
|------|-------|--------|
| `query_transactions` | `{date_start?, date_end?, category?, merchant?, min_amount?, max_amount?, limit?}` | Array of transactions with amounts, dates, categories |
| `get_budget` | `{month?}` | Current period budget: income, allocations, spent, remaining per category |
| `get_debt_status` | `{}` | All debts: balances, APRs, minimums, projected payoff dates |
| `ask_financial_question` | `{question: string}` | Natural language answer with supporting data |
| `get_report` | `{month?}` | Monthly report markdown + structured data |
| `get_insights` | `{status?: active\|dismissed}` | Active insights with type, severity, and recommendations |

**Implementation steps**:
1. Install `laravel/mcp` if not already in `composer.json`
2. Create tool classes in `app/MCP/Tools/` — each implements `__invoke()` with typed input
3. Register tools in `CashbirdMcpServer::register()`, called from `AppServiceProvider`
4. Configure MCP transport: `web` (HTTP/SSE) for remote access, `local` (stdio) for CLI
5. Auth: MCP requests are authenticated via WorkOS session or API token

**Feedback loop**:
- **Playground**: Start MCP server `php artisan mcp:serve`, test tools via Claude Code or `curl`
- **Experiment**: Call each tool with valid inputs, verify responses. Test `query_transactions` with date range, `get_budget` for current month, `ask_financial_question` with a test query.
- **Check command**: `php artisan test --filter=McpTools`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/ReportAgentTest.php` | Report generation with various data states |
| `tests/Feature/InsightsAgentTest.php` | Each insight type detection |
| `tests/Feature/QueryAgentTest.php` | NLQ parsing and response formatting |
| `tests/Feature/McpToolsTest.php` | Each MCP tool invocation and response schema |

**Key test cases**:
- Monthly report includes all sections when data is complete
- Report handles gracefully when no budget or no debt exists
- Insights detect unused subscriptions from recurring charges
- Insights don't re-surface dismissed items
- QueryAgent answers date-range questions correctly
- MCP `query_transactions` returns filtered results
- MCP `get_budget` returns current period data
- MCP tools return proper error responses for invalid inputs
- MCP auth rejects unauthenticated requests

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| ReportAgent | Incomplete data | Phase 3 categorization not finished for the month | Report has "Uncategorized" as top category | Run report generation 3 days after month end; warn in report if >10% uncategorized |
| InsightsAgent | False positive insights | Unusual but legitimate spending (vacation, medical) | User gets annoying/wrong recommendations | Allow dismiss; track dismissal patterns; lower confidence threshold for similar future insights |
| QueryAgent | Wrong answer | AI misinterprets question or queries wrong data | User acts on incorrect information | Always show source data with answer; include "data as of" timestamp |
| MCP Server | Unauthorized access | Token stolen or misconfigured auth | Financial data exposed | WorkOS session validation on every MCP request; rate limiting; audit log |
| MCP Server | Tool timeout | Complex query takes too long | MCP client shows error | 30s timeout per tool; for complex queries, return partial results with note |

## Validation Commands

```bash
# Run migrations
php artisan migrate

# Run all Phase 6 tests
php artisan test --filter=Report
php artisan test --filter=Insights
php artisan test --filter=Query
php artisan test --filter=McpTools

# Start MCP server
php artisan mcp:serve

# Manual: generate report for last month
# php artisan tinker --execute="dispatch(new GenerateMonthlyReport(User::first(), now()->subMonth()));"

# Manual: test MCP tool
# In Claude Code: connect to Cashbird MCP server and ask "What's my budget status?"
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
