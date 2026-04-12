# Context Map: Stripe Financial Connections

**Phase**: 2 (extended from Phase 1)
**Scout Confidence**: 91/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 20/20 | All files read; new/modified/deleted files fully understood |
| Pattern familiarity | 19/20 | TellerController pattern and Stripe client API both read; minor gap is Stripe.js FC callback shape |
| Dependency awareness | 18/20 | account_holder.type needs fix; everything else fully mapped |
| Edge case coverage | 17/20 | CSRF on fetch, DB transaction wrapping, duplicate constraint path known |
| Test strategy | 17/20 | Controller tests use $this->mock() on StripeFinancialConnectionsClient singleton |

## Key Patterns (Phase 2)

- TellerController: no constructor injection, client injected as method param, $request->user() + assert, UniqueConstraintViolationException catch
- ConnectAccount: pure render container, passes config to view, @push('scripts') for JS
- Routes: all in auth:workos group, named routes

## Risks

- **account_holder.type = 'customer'**: Must fix to 'individual' in StripeFinancialConnectionsClient::createSession — Cashbird has no Stripe Customer IDs
- **CSRF on fetch**: Must include X-CSRF-TOKEN header in Alpine fetch calls
- **Stripe.js callback**: Returns { financialConnectionsSession, error } — must handle error branch
- **DB transaction**: store() must wrap in DB::transaction() for partial failure rollback
- **SyncAllAccounts blast radius**: Still uses TellerClient internally — will fail at runtime until Phase 3
