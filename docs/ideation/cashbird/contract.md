# Cashbird Contract

**Created**: 2026-04-10
**Confidence Score**: 95/100
**Status**: Approved
**Supersedes**: None

## Problem Statement

Nick makes strong income but has no visibility into where money goes, no disciplined budgeting system, and is currently in active debt recovery (credit cards, payday loans from services like MoneyLion/Cleo). The result is good earnings with poor net worth trajectory. Manual budgeting has never stuck — the overhead of categorizing, setting limits, and reviewing is exactly the kind of work that falls off under time pressure.

The core need is an AI-driven personal finance system that removes the human from the budgeting loop as much as possible. The AI should observe spending, detect patterns, generate budgets, propose adjustments, and surface actionable insights — so the human only needs to approve, override, or ask questions.

This is a family tool (3 users: Nick, partner, child) with shared and private financial views, self-hosted to minimize costs.

## Goals

1. **Full financial visibility** — Connect all account types (checking, savings, credit cards, loans, investments, payday loans) via Teller and display categorized transaction history going as far back as the API provides
2. **AI-generated zero-based budget** — AI analyzes historical spending, auto-detects fixed bills via recurring charge fingerprinting, and generates a complete budget where every dollar is assigned. User locks non-negotiable bills (rent, child support, car); AI controls everything else.
3. **Continuous "ready to spend" monitoring** — Background agent tracks `(allocated - posted - pending) / days_remaining` per category in real-time, surfacing daily safe-to-spend amounts
4. **Monthly budget proposals** — AI generates diff-based budget adjustments monthly with rationale strings, presented conversationally for user approval before taking effect
5. **Debt payoff acceleration** — APR-sorted avalanche strategy with snowball rollup on payoff milestones. Track all debt including payday loans and credit card recovery plans with projected payoff timelines.
6. **Natural language financial queries** — MCP server and/or chat UI for questions like "how much did I spend on dining in March?" answered from transaction data via Laravel AI SDK agents
7. **Family access control** — Per-user private accounts and budgets, plus shared budget categories (rent, groceries) with WorkOS FGA governing who can view/edit shared items

## Success Criteria

- [ ] User can connect bank accounts via Teller (checking, savings, credit cards, loans, investments)
- [ ] All available historical transactions are pulled and stored in PostgreSQL
- [ ] AI auto-categorizes transactions with >90% accuracy on common merchants, with user override capability
- [ ] AI generates an initial zero-based budget from historical data within 5 minutes of first sync
- [ ] User can mark bills as non-negotiable (locked); AI auto-detects recurring charges and classifies as fixed
- [ ] "Ready to spend" amount updates within 60 seconds of new transaction via Redis pub/sub
- [ ] Monthly budget proposal is generated as a diff with per-line rationale, viewable in UI
- [ ] Debt dashboard shows all debts sorted by APR with projected payoff dates at current payment rate
- [ ] Natural language queries return accurate answers via MCP server tools
- [ ] Multiple users can sign in via WorkOS AuthKit; each user connects their own accounts
- [ ] Shared budget categories are visible to authorized users via WorkOS FGA warrants
- [ ] Application deploys on Beelink server via Coolify with zero ongoing SaaS costs (Teller free tier, WorkOS free tier)

## Scope Boundaries

### In Scope

- Teller.io integration for bank account connection (free tier, 100 connections)
- Transaction ingestion, storage, and AI-powered categorization
- AI-generated zero-based budgets with locked non-negotiable bills and auto-detected fixed charges
- Real-time "ready to spend" tracking per category via Redis
- Monthly AI budget proposals (diff-based, conversational approval)
- Debt tracking and avalanche payoff strategy with projected timelines
- Spending reports and narrative AI-generated monthly summaries
- Natural language queries via laravel/mcp and Laravel AI SDK agents
- WorkOS AuthKit authentication via birdcar/authkit-laravel
- WorkOS FGA for shared budget item authorization
- WorkOS Pipes for Google OAuth (potential Gmail receipt integration)
- PostgreSQL database, Redis for realtime/queues
- Self-hosted deployment via Coolify on Beelink server
- Livewire UI (aligned with authkit-laravel's existing widget pattern)

### Out of Scope

- Write operations (moving money between accounts, initiating payments) — V2
- Mobile app — web-only for V1
- Laravel starter kit — using authkit-laravel with custom UI
- Laravel Cashier — no billing, this is an internal family app
- Organizations/teams in WorkOS — user-level only with FGA for sharing
- Investment portfolio analysis (holdings, returns, rebalancing) — V1 tracks balances only
- Tax preparation or tax-optimized suggestions
- Receipt OCR or email receipt parsing (Pipes connection is for future use)
- Multi-currency support

### Future Considerations

- V2: Write operations via Teller (transfers, bill pay)
- Gmail receipt matching via WorkOS Pipes + Gmail API
- Investment analysis beyond balance tracking
- Mobile app or PWA
- Plaid as fallback if Teller changes pricing or coverage gaps emerge
- Budget sharing/collaboration features beyond view/edit permissions
- Automated savings rules (round-ups, percentage-of-income sweeps)

## Execution Plan

### Dependency Graph

```
Phase 1: Foundation & Auth
 └─ Phase 2: Teller & Data Layer
     ├─ Phase 3: AI Categorization ──┐
     │   └─ Phase 4: Budget Engine   │ parallel
     │       ├─ Phase 6: AI & MCP ──┐│
     │       └─ Phase 7: FGA ───────┘│ parallel
     └─ Phase 5: Debt Tracking ──────┘ parallel with P3
```

### Execution Steps

**Strategy**: Hybrid (sequential chain with two parallel windows)

1. **Phase 1 — Foundation & Auth** _(blocking, sequential)_
   ```bash
   /execute-spec docs/ideation/cashbird/spec-phase-1.md
   ```

2. **Phase 2 — Teller & Data Layer** _(blocked by Phase 1, sequential)_
   ```bash
   /execute-spec docs/ideation/cashbird/spec-phase-2.md
   ```

3. **Phases 3 & 5 — parallel after Phase 2**
   Both depend only on Phase 2. Run as agent team or sequentially:
   ```bash
   /execute-spec docs/ideation/cashbird/spec-phase-3.md
   /execute-spec docs/ideation/cashbird/spec-phase-5.md
   ```

4. **Phase 4 — Budget Engine** _(blocked by Phase 3, sequential)_
   ```bash
   /execute-spec docs/ideation/cashbird/spec-phase-4.md
   ```

5. **Phases 6 & 7 — parallel after Phase 4**
   Both depend only on Phase 4. Run as agent team or sequentially:
   ```bash
   /execute-spec docs/ideation/cashbird/spec-phase-6.md
   /execute-spec docs/ideation/cashbird/spec-phase-7.md
   ```

### Agent Team Prompt — Phases 3 & 5

Start a Claude Code session, enter delegate mode (Shift+Tab), paste:

```
Phase 2 (Teller & Data Layer) is complete. Create an agent team to
implement 2 phases in parallel. Each phase is independent — they share
no files.

Spawn 2 teammates with plan approval required. Each teammate should:
1. Read their assigned spec file
2. Explore the codebase for relevant patterns before planning
3. Plan their implementation approach and wait for approval
4. Implement following spec and codebase patterns
5. Run validation commands from their spec after implementation

Teammates:

1. "AI Categorization" — docs/ideation/cashbird/spec-phase-3.md
   Laravel AI SDK agent for transaction categorization, category hierarchy,
   override learning, and spending aggregation.

2. "Debt Tracking" — docs/ideation/cashbird/spec-phase-5.md
   Debt models, APR-sorted avalanche calculator, payoff projections,
   manual debt entry for payday loans.
```

### Agent Team Prompt — Phases 6 & 7

Start a Claude Code session, enter delegate mode (Shift+Tab), paste:

```
Phase 4 (Budget Engine) is complete. Create an agent team to implement
2 remaining phases in parallel.

Spawn 2 teammates with plan approval required. Each teammate should:
1. Read their assigned spec file
2. Explore the codebase for relevant patterns before planning
3. Plan their implementation approach and wait for approval
4. Implement following spec and codebase patterns
5. Run validation commands from their spec after implementation

Coordinate on shared files: routes/web.php, sidebar.blade.php, dashboard.blade.php
— only one teammate should modify a shared file at a time.

Teammates:

1. "AI Insights & MCP" — docs/ideation/cashbird/spec-phase-6.md
   Monthly reports, spending insights, natural language queries,
   MCP server with 6 tools via laravel/mcp.

2. "Family & FGA" — docs/ideation/cashbird/spec-phase-7.md
   WorkOS FGA authorization schema, warrant management, sharing UI,
   access middleware for budget resources.
```

---

_This contract was generated from brain dump input and approved on 2026-04-10._
