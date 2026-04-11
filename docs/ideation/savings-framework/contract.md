# Savings Framework Contract

**Created**: 2026-04-10
**Confidence Score**: 96/100
**Status**: Draft
**Supersedes**: None

## Problem Statement

Cashbird's budget system is purely backward-looking. It analyzes 3 months of spending history and allocates 100% of income proportionally to spending categories. This perpetuates existing habits rather than helping the household improve them.

There's no savings target — every dollar of income gets distributed to spending. There's no net worth tracking — account balances and debts exist independently but are never aggregated. The debt payoff planner and budget system don't coordinate — when a debt is paid off and its payment is freed, nothing happens. The household has no way to know if they're making progress toward financial stability.

The result: the app answers "what can I spend?" but never "am I getting ahead?" For a household working through debt and trying to build savings, that's the more important question.

## Goals

1. **Implement 50/30/20 framework** — The budget engine carves out savings before distributing to spending categories. The AI classifies each category as needs/wants/savings+debt, with user overrides. "Safe to spend today" already accounts for savings contributions.

2. **Progressive savings goals** — Guide the household through a research-backed progression: starter emergency fund ($1,000) → aggressive debt payoff → full emergency fund (3-6 months expenses) → named goals with target amounts and dates. The AI recommends the current stage and next steps.

3. **Net worth tracking** — Aggregate all account balances minus all debts into a single number. Track monthly trend. Show on dashboard as a summary card and on a dedicated page with historical chart and per-account breakdown.

4. **Debt-to-savings coordination** — When a debt is paid off, continue snowballing freed payments to remaining debts. Once all debts are paid, automatically redirect the freed total toward savings goals via a budget proposal.

5. **Goal psychology** — Named goals with progress visualization (progress bars, milestone markers at 25/50/75/100%), projected completion dates based on contribution rate, and "on track / behind" status indicators.

## Success Criteria

- [ ] Budget allocation reserves savings percentage before distributing to needs/wants categories
- [ ] Each spending category has a needs/wants/savings classification (AI-assigned, user-overridable)
- [ ] "Safe to spend today" in the hero card and sidebar widget reflects the savings-adjusted budget, not the full income
- [ ] Savings goals exist as a data model with name, target amount, target date, and current balance
- [ ] The AI recommends the appropriate savings stage based on current debt load and emergency fund status
- [ ] Dashboard shows net worth summary card (total assets - total debts, month-over-month change)
- [ ] Dedicated net worth page shows historical monthly trend chart and per-account/debt breakdown
- [ ] When a debt is paid off, freed payment snowballs to remaining debts (existing behavior preserved)
- [ ] When all debts are paid off, a budget proposal is generated redirecting freed payments to savings
- [ ] Savings goals show progress bars with percentage, projected completion date, and on-track status
- [ ] 50/30/20 split is visible in budget overview (needs total / wants total / savings+debt total)
- [ ] All existing tests continue to pass; new tests cover savings calculations, goal progression, and net worth aggregation

## Scope Boundaries

### In Scope

- 50/30/20 budget framework with AI category classification
- `SavingsGoal` model with name, target amount, target date, current balance, priority, status
- `SavingsStage` enum (StarterEmergencyFund, DebtPayoff, FullEmergencyFund, NamedGoals)
- AI agent for recommending savings stage and goal parameters
- Net worth calculation from existing account balances and debt balances
- Net worth history tracking (monthly snapshots)
- Dashboard net worth summary card
- Dedicated net worth page with Livewire component
- Savings goals page with Livewire component (list, create, progress)
- Budget proposal generation when debts are fully paid off
- Category classification (needs/wants) stored per-category with AI defaults
- Modification of `BudgetCalculator` to reserve savings allocation before discretionary distribution
- Modification of `ReadyToSpend` to subtract savings contribution from available spending
- Sidebar widget reflects savings-adjusted safe-to-spend
- Tests for all new services and models

### Out of Scope

- Investment tracking or portfolio management — Cashbird tracks bank accounts, not brokerage accounts
- Retirement-specific calculations (401k projections, compound interest modeling) — too complex for v1
- Linked savings accounts via Teller for goal progress — goals track manual or inferred balances, not real account mappings (deferred to future)
- Changes to the MCP server tools — can be added after core features stabilize
- Changes to the insights agent — it already has `SavingsOpportunity` type; can be enhanced later

### Future Considerations

- Link savings goals to specific Teller-connected savings accounts for real-time progress
- Retirement goal type with compound interest projections
- MCP tools for querying savings goals and net worth
- Insights agent enhancement: "You're $200 ahead on your emergency fund this month"
- Celebration animations when goals hit milestones (25/50/75/100%)

## Execution Plan

### Dependency Graph

```
Phase 1: Data Foundation (models, migrations, enums)
  ├── Phase 2: Budget Engine Overhaul (50/30/20, AI classification, ReadyToSpend)
  │     └── Phase 4: Savings Goals UI + Debt Coordination (blocked by 1 + 2)
  └── Phase 3: Net Worth Tracking (calculator, snapshots, dashboard card, page)
```

Phases 2 and 3 are parallelizable after Phase 1. Phase 4 depends on both 1 and 2.

### Execution Steps

**Strategy**: Hybrid — Phase 1 sequential, Phases 2 & 3 parallel, Phase 4 sequential after 2.

1. **Phase 1** — Data Foundation _(blocking)_
   ```bash
   /execute-spec docs/ideation/savings-framework/spec-phase-1.md
   ```

2. **Phases 2 & 3** — parallel after Phase 1
   ```bash
   /execute-spec docs/ideation/savings-framework/spec-phase-2.md
   /execute-spec docs/ideation/savings-framework/spec-phase-3.md
   ```

3. **Phase 4** — Savings Goals UI + Debt Coordination _(blocked by Phase 2)_
   ```bash
   /execute-spec docs/ideation/savings-framework/spec-phase-4.md
   ```

### Agent Team Prompt

```
You are coordinating the Savings Framework build for Cashbird.

Phase 1 is complete. Execute Phases 2 and 3 in parallel:

Teammate 1 — Budget Engine Overhaul:
  Spec: docs/ideation/savings-framework/spec-phase-2.md
  Focus: BudgetCalculator 50/30/20 split, ReadyToSpend savings deduction,
  CategoryClassifierAgent, SavingsStageAdvisor, budget overview view changes.

Teammate 2 — Net Worth Tracking:
  Spec: docs/ideation/savings-framework/spec-phase-3.md
  Focus: NetWorthCalculator, SnapshotNetWorth job, dashboard card,
  /net-worth page with trend chart.

Coordinate on shared files (routes/web.php, sidebar.blade.php) to avoid
merge conflicts — only one teammate should modify a shared file at a time.
Teammate 1 takes routes/web.php first, Teammate 2 adds its route after.

After both complete, run the full test suite: php artisan test --compact
```
