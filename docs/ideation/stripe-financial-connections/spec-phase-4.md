# Implementation Spec: Stripe Financial Connections - Phase 4

**Contract**: ./contract.md
**Estimated Effort**: S

## Technical Approach

Final cleanup phase. Remove any remaining Teller artifacts, run a full codebase sweep for stale references, update the README and `.env.example` to reflect the Stripe Financial Connections integration, and run the full test suite to confirm nothing is broken.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact`

**Playground**: Full test suite -- this phase is about verifying the whole system works together.

**Why this approach**: Cleanup and docs don't need a tight feedback loop. The full test suite is the final validation that everything holds together.

## File Changes

### Modified Files

| File Path | Changes |
|---|---|
| `README.md` | Replace all Teller references with Stripe FC: tech stack, setup instructions, env vars, sync description |
| `.env.example` | Verify Teller vars are gone, Stripe vars are present and documented |
| `app/Providers/AppServiceProvider.php` | Verify no Teller imports remain |
| `CLAUDE.md` | Update if it references Teller anywhere |

### Deleted Files (if not already removed in prior phases)

| File Path | Reason |
|---|---|
| `app/Services/Teller/` (directory) | Entire Teller service directory |
| `app/Http/Controllers/TellerController.php` | Already removed in Phase 2, verify |
| `app/Models/TellerEnrollment.php` | Already removed in Phase 1, verify |
| `config/teller.php` | Already removed in Phase 1, verify |
| `database/factories/TellerEnrollmentFactory.php` | Already removed in Phase 1, verify |
| `tests/Feature/TellerClientTest.php` | Already removed in Phase 3, verify |
| `tests/Feature/TellerEnrollmentTest.php` | Already removed in Phase 2, verify |

## Implementation Details

### Codebase Sweep

**Overview**: Grep the entire codebase for any remaining Teller references and remove them.

**Implementation steps**:

1. Run: `grep -rn "teller\|Teller\|TELLER" --include="*.php" --include="*.blade.php" --include="*.js" --include="*.md" --include="*.env*" --exclude-dir=vendor --exclude-dir=node_modules .`
2. For each hit:
   - If it's a file that should have been deleted in a prior phase: delete it
   - If it's a reference in a file that's being kept: update the reference
   - If it's in a migration file (historical): leave it -- migrations are immutable history
3. Verify the `app/Services/Teller/` directory is completely gone
4. Verify no `use App\Services\Teller\TellerClient` imports exist anywhere
5. Verify no `use App\Models\TellerEnrollment` imports exist anywhere

### README Update

**Pattern to follow**: Current `README.md` structure and tone

**Overview**: Update the README to replace Teller with Stripe Financial Connections throughout.

**Specific changes**:

1. **"What it does" section** -- "Bank account sync" paragraph:
   - Change: "Connects to bank accounts through the [Teller API](https://teller.io/)"
   - To: "Connects to bank accounts through [Stripe Financial Connections](https://stripe.com/financial-connections)"
   - Update sync description: "Transactions sync daily via Stripe's subscription API, with a manual refresh button for on-demand updates."

2. **"Tech stack" section**:
   - Change: `- **Teller API** -- bank account connections and transaction sync`
   - To: `- **Stripe Financial Connections** -- bank account linking, transaction sync, and balance data via Stripe.js modal and subscription-based daily refresh`

3. **"Configure external services" section** -- replace "3. Teller (bank connections)" entirely:
   ```markdown
   #### 3. Stripe Financial Connections

   Stripe Financial Connections provides bank account linking and transaction/balance sync. AI features work without Stripe (you just won't have any transaction data).

   1. Go to [Stripe Dashboard](https://dashboard.stripe.com) > **Developers** > **API keys**
   2. Note your **Publishable key** (starts with `pk_`) and **Secret key** (starts with `sk_`)
   3. Go to **Developers** > **Webhooks** > **Add endpoint**
      - URL: `https://your-domain.com/stripe/webhook` (or your local tunnel URL for development)
      - Events: `financial_connections.account.refreshed_transactions_data`, `financial_connections.account.refreshed_balance`, `financial_connections.account.disconnected`
   4. Note the **Webhook signing secret** (starts with `whsec_`)
   5. Set in `.env`:
      ```
      STRIPE_SECRET_KEY=sk_...
      STRIPE_PUBLISHABLE_KEY=pk_...
      STRIPE_WEBHOOK_SECRET=whsec_...
      ```

   Transactions sync automatically once daily via Stripe's subscription API. Stripe sends a webhook when fresh data is available, and the app pulls updated transactions. You can also trigger a manual sync from the Accounts page.

   > **Note:** For local development without a public URL, the daily fallback schedule (runs at 23:00) will sync transactions even without webhooks. You can also use [Stripe CLI](https://stripe.com/docs/stripe-cli) to forward webhooks locally: `stripe listen --forward-to localhost:8000/stripe/webhook`
   ```

4. **"Local setup" > "Run" section** -- no changes needed (same processes)

5. **"Deployment" > "Environment variables"** -- update the Teller line:
   - Remove: `- Teller: \`TELLER_APP_ID\`, cert/key paths`
   - Add: `- Stripe: \`STRIPE_SECRET_KEY\`, \`STRIPE_PUBLISHABLE_KEY\`, \`STRIPE_WEBHOOK_SECRET\``

6. **"Project structure" section** -- update Services description:
   - Change: `Services/          # Business logic (budget calculation, debt projection, Teller client)`
   - To: `Services/          # Business logic (budget calculation, debt projection, Stripe FC client)`

### .env.example Verification

**Implementation steps**:
1. Verify these are gone: `TELLER_APP_ID`, `TELLER_BASE_URL`, `TELLER_CERT_PATH`, `TELLER_KEY_PATH`
2. Verify these are present with documentation comments:
   ```
   STRIPE_SECRET_KEY=
   STRIPE_PUBLISHABLE_KEY=
   STRIPE_WEBHOOK_SECRET=
   ```

### Full Test Suite

**Implementation steps**:
1. Run `php artisan test --compact` -- all 243+ tests must pass (minus removed Teller tests, plus new Stripe FC tests)
2. Fix any failures caused by stale references
3. Verify test count makes sense (Teller tests removed, Stripe FC tests added)

## Testing Requirements

### Integration Test (Full Suite)

**Key scenarios**:
- Full test suite passes with no Teller-related failures
- No test references `TellerClient`, `TellerEnrollment`, or `teller_id`
- New Stripe FC tests all pass

### Manual Testing

- [ ] Read through README -- all instructions are accurate and complete
- [ ] Follow README setup instructions for Stripe FC from scratch -- they work
- [ ] `.env.example` has correct vars, no stale Teller vars
- [ ] `grep -r "teller" --include="*.php" --exclude-dir=vendor .` returns only migration files

## Validation Commands

```bash
# Full codebase sweep for Teller references (excluding vendor, node_modules, migrations)
grep -rn "teller\|Teller\|TELLER" --include="*.php" --include="*.blade.php" --include="*.js" --include="*.env*" --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=database/migrations .

# Full test suite
php artisan test --compact

# Pint formatting
vendor/bin/pint --dirty --format agent
```
