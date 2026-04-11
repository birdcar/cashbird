# Cashbird

A personal finance app for my household. It answers the two questions we ask every day: "what can we spend?" and "where did our money go?"

It's not a SaaS product or a startup -- it's a family tool that connects to our bank accounts, categorizes transactions, builds budgets with AI, and gives us a shared view of our money. My girlfriend and daughter use it too, so the interface is designed to be clear even if you don't think about money all day.

<!-- TODO: screenshot of the dashboard -->

## What it does

**Daily spending allowance.** The dashboard opens with a "safe to spend" number -- how much is left in each budget category divided by the remaining days in the month. This is the thing we actually check every morning.

**AI-generated budgets.** Instead of manually setting up budget categories, Cashbird analyzes your spending history and proposes a budget. You review and adjust the suggestions, but the starting point is real data rather than guesswork.

**Bank account sync.** Connects to bank accounts through the [Teller API](https://teller.io/) to pull in transactions automatically. Transactions get categorized by an AI agent, with the option to override categories manually.

**Debt payoff planner.** Tracks debts with balances, interest rates, and minimum payments, then projects a payoff timeline using the avalanche method. Shows you a "debt-free by" date.

**AI insights and reports.** Generates periodic observations about spending patterns -- things like "your dining spend increased 23% this month" or "you have 3 recurring charges you might want to review." Monthly reports summarize everything.

**Natural language chat.** An "Ask Cashbird" interface where you can type questions like "how much did I spend on groceries in March?" and get answers from your actual transaction data.

**Household sharing.** Multiple household members can access shared budgets and categories through WorkOS FGA (fine-grained authorization). Not multi-tenant SaaS -- just a family sharing one set of finances.

**MCP server.** Exposes financial data through a Model Context Protocol server, so you can query budgets, transactions, debt status, and insights from Claude or other MCP clients.

## Tech stack

- **PHP 8.4 / Laravel 13** -- the framework handles routing, queues, scheduling, and the AI SDK integration
- **Livewire 4** -- all interactive UI is server-rendered Livewire components (no React/Vue)
- **Alpine.js** -- client-side interactions like keyboard shortcuts and the command palette
- **Tailwind CSS v4** -- warm OKLCH color palette (sand, amber, sage, terracotta) instead of the usual cold grays
- **WorkOS AuthKit** -- authentication and fine-grained authorization for household sharing
- **Teller API** -- bank account connections and transaction sync
- **Laravel AI SDK** -- powers budget generation, transaction categorization, insights, reports, and the chat interface
- **PostgreSQL** -- primary database
- **Redis** -- queues and caching
- **Vite + Bun** -- frontend build tooling

### Design details

The visual direction is warm and calm -- cream backgrounds, Fraunces for display headings, Plus Jakarta Sans for body text, and Phosphor Icons throughout. The landing page has a Stripe-inspired scroll animation built with GSAP and ScrollTrigger. The app supports keyboard navigation (`g` then a letter to jump to any section, `Cmd+K` for a command palette, `?` for shortcut help) and contextual help tooltips on financial terms.

## Local setup

### Prerequisites

- PHP 8.4+
- Composer
- PostgreSQL
- Redis
- Bun (or Node.js, but Bun is preferred)

### Install

```bash
git clone git@github.com:birdcar/cashbird.git
cd cashbird
composer setup
```

The `composer setup` script handles `composer install`, copying `.env.example`, generating an app key, running migrations, installing JS dependencies, and building frontend assets.

### Configure

Copy `.env.example` to `.env` (done by `composer setup`) and fill in:

- **Database** -- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for your local PostgreSQL
- **WorkOS** -- `WORKOS_CLIENT_ID`, `WORKOS_API_KEY`, `WORKOS_REDIRECT_URI` for authentication
- **Teller** -- `TELLER_APP_ID`, cert/key paths, and signing secret for bank connections
- **AI** -- whatever provider keys the Laravel AI SDK needs (OpenAI, Anthropic, etc.)

### Run

```bash
composer run dev
```

This starts the Laravel dev server, queue worker, log tail (Pail), and Vite dev server concurrently.

## Tests

156 PHPUnit tests covering authentication, budget calculations, transaction sync, debt projections, AI agents, sharing flows, and more.

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/ReadyToSpendTest.php

# Filter by test name
php artisan test --compact --filter=testAvalancheCalculator
```

## Project structure

```
app/
  Livewire/          # Livewire components (Dashboard, Budget, Debt, Chat, etc.)
  Mcp/               # MCP server and tools
  Services/          # Business logic (budget calculation, debt projection, Teller client)
resources/
  views/livewire/    # Blade templates for each Livewire component
  views/components/  # Shared UI components (help tooltips, layout)
  views/welcome.blade.php  # Landing page
tests/
  Feature/           # 26 feature test classes
  Unit/              # Unit tests
```

## License

MIT
