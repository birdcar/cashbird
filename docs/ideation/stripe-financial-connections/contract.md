# Replace Teller with Stripe Financial Connections

**Created**: 2026-04-12
**Confidence Score**: 96/100
**Status**: Approved
**Supersedes**: None

## Problem Statement

Cashbird connects to bank accounts through Teller.io for transaction sync. Teller charges a $1,000/month platform fee on top of per-API-call pricing. For a household finance app with 3 users and fewer than 30 accounts, this is untenable. Additionally, the Teller onboarding process has stalled -- no access email has arrived, making the integration non-functional.

Stripe Financial Connections provides the same core capability (bank account linking + transaction/balance data) at pay-as-you-go pricing. For Cashbird's scale: ~$15 one-time connection cost + ~$60-90/month ongoing for daily balance and transaction refreshes. Not cheap for a household tool, but 10-15x cheaper than Teller's platform fee.

## Goals

1. **Replace Teller integration entirely** with Stripe Financial Connections -- new API client, new enrollment flow, new sync engine
2. **Simplify sync cadence** to once-daily transaction sync (end of day) plus on-demand manual refresh, replacing the current hourly incremental + 6-hour full sync
3. **Use provider-agnostic naming** in the data layer (`connections` table, `external_id` columns) to avoid repeating a vendor lock-in migration
4. **Preserve the downstream event pipeline** -- TransactionsSynced, categorization, debt sync, spending cache, and all listeners must continue working unchanged
5. **Fresh start on data** -- drop Teller-specific tables and columns, users re-link accounts through Stripe FC
6. **Update README and docs** to reflect the new integration, setup steps, and env vars

## Success Criteria

- [ ] Users can link bank accounts through the Stripe.js Financial Connections modal
- [ ] Linked accounts appear in the Accounts page with balance data
- [ ] Transactions sync automatically once daily via Stripe FC subscription + webhook
- [ ] Manual "Sync Now" triggers an on-demand refresh (subject to Stripe's `next_refresh_available_at` throttle)
- [ ] TransactionsSynced event fires after sync, triggering categorization, debt sync, and cache invalidation
- [ ] All Teller code, config, env vars, and tests are removed
- [ ] New test suite covers: FC client methods, connection flow, transaction sync, webhook handling, 401/error scenarios
- [ ] No `teller_id` or `teller_enrollments` references remain anywhere in the codebase
- [ ] README accurately documents Stripe FC setup, env vars, and sync behavior
- [ ] `.env.example` has correct Stripe env vars, no Teller env vars

## Scope Boundaries

### In Scope

- Install `stripe/stripe-php` SDK
- Stripe config file and env vars (`STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`)
- New `connections` table replacing `teller_enrollments` (provider-agnostic naming)
- Rename `teller_id` to `external_id` on `accounts` and `transactions` tables
- Rename `teller_id` to `external_id` on `institutions` table
- `StripeFinancialConnectionsClient` service class
- New `FinancialConnectionsController` for session creation and connection handling
- Replace Teller Connect JS SDK with Stripe.js `collectFinancialConnectionsAccounts`
- Webhook controller for `financial_connections.account.refreshed_transactions` and related events
- Stripe FC subscription API for daily transaction/balance refresh
- Updated schedule in `routes/console.php`
- Updated `SyncAllAccounts` and `SyncAccountTransactions` jobs
- Manual "Sync Now" using Stripe FC refresh API
- New PHPUnit tests for all new code
- Delete all Teller-specific code, config, tests, factories
- Update README (tech stack, setup instructions, env vars, sync description)
- Update `.env.example`

### Out of Scope

- Migrating existing transaction/account data from Teller IDs to Stripe FC IDs -- fresh start
- Changing the downstream event pipeline (categorization, debt sync, spending cache, insights)
- Any changes to the budget, debt, reports, chat, or sharing features
- Real-time transaction notifications (Stripe FC syncs at most once/day per bank)
- Multi-provider support or provider abstraction layer -- just replace Teller with Stripe FC

### Future Considerations

- If Stripe FC pricing becomes too high, evaluate Plaid (free dev tier, ~$0.30/account production) or MX as alternatives
- Transaction enrichment (merchant names, categories) from Stripe FC is limited to raw descriptions -- may want a separate enrichment service later
- Stripe FC may add real-time sync or cheaper pricing tiers in the future

## Execution Plan

### Dependency Graph

```
Phase 1: Infrastructure (Stripe SDK, schema, client, models)
  ├── Phase 2: Account Linking (Stripe.js modal, controller, routes)
  └── Phase 3: Sync Engine (webhooks, daily sync, manual refresh)
        ├── Phase 4: Cleanup + Docs (remove Teller, update README)
Phase 2 ──┘
```

### Execution Steps

**Strategy**: Hybrid (Phase 1 sequential, Phases 2 & 3 parallel, Phase 4 sequential)

1. **Phase 1 -- Infrastructure** _(blocking, must complete first)_
   ```bash
   /execute-spec docs/ideation/stripe-financial-connections/spec-phase-1.md
   ```

2. **Phases 2 & 3 -- parallel after Phase 1**

   Start one Claude Code session, enter delegate mode (Shift+Tab), paste the agent team prompt below.

   Or run sequentially:
   ```bash
   /execute-spec docs/ideation/stripe-financial-connections/spec-phase-2.md
   /execute-spec docs/ideation/stripe-financial-connections/spec-phase-3.md
   ```

3. **Phase 4 -- Cleanup + Docs** _(blocked by Phases 2 & 3)_
   ```bash
   /execute-spec docs/ideation/stripe-financial-connections/spec-phase-4.md
   ```

### Agent Team Prompt

```
Phase 1 (Infrastructure) is complete. Create an agent team to implement
2 remaining phases in parallel. Each phase is independent.

Spawn 2 teammates with plan approval required. Each teammate should:
1. Read their assigned spec file
2. Explore the codebase for relevant patterns before planning
3. Plan their implementation approach and wait for approval
4. Implement following spec and codebase patterns
5. Run validation commands from their spec after implementation

Teammates:

1. "Account Linking" — docs/ideation/stripe-financial-connections/spec-phase-2.md
   Replace Teller Connect JS with Stripe.js modal, new controller for FC sessions,
   update Livewire component and routes.

2. "Sync Engine" — docs/ideation/stripe-financial-connections/spec-phase-3.md
   Webhook handler for Stripe FC events, rewrite sync jobs to use Stripe FC API,
   daily subscription sync, manual refresh button, update schedule.

Coordinate on shared files (routes/web.php, app/Livewire/Accounts/AccountList.php)
to avoid merge conflicts — only one teammate should modify a shared file at a time.
```
