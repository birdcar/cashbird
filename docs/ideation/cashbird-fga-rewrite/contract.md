# Cashbird FGA Rewrite Contract

**Created**: 2026-04-11
**Confidence Score**: 96/100
**Status**: Pending Approval
**Supersedes**: None (Phase 7 FGA layer is being rewritten, not extended)

## Problem Statement

The Cashbird FGA layer was built against the deprecated WorkOS warrant/tuple-based API (`/fga/v1/warrants`, `/fga/v1/check`). WorkOS has moved to a resource-based authorization model using `/authorization/resources`, `/authorization/organization_memberships/:id/role_assignments`, and `/authorization/organization_memberships/:id/check`. The deprecated endpoints will eventually stop working, and the current implementation cannot be deployed against a real WorkOS environment.

Additionally, the MCP server uses session-based `auth:workos` middleware which only works for same-browser access. External MCP clients (Claude Desktop, Claude Code) need OAuth 2.1 authentication via WorkOS Connect.

## Goals

1. **Rewrite FGAService** to use the current WorkOS authorization API (`/authorization/*` endpoints)
2. **Use organization memberships** as the identity model — a single family org, membership IDs fetched from WorkOS API and cached
3. **Register FGA resources** in WorkOS when budget categories are created/shared
4. **Add WorkOS Connect OAuth** for MCP server authentication alongside existing session auth
5. **Update all callers** (ShareBudgetCategory, ManageSharing, CheckBudgetAccess, AllocationEditor, BudgetOverview) with minimal interface changes
6. **Document dashboard setup** — resource types, roles, and permissions needed in WorkOS Dashboard

## Success Criteria

- [ ] FGAService uses `/authorization/resources`, `/authorization/organization_memberships/:id/role_assignments`, and `/authorization/organization_memberships/:id/check`
- [ ] Organization membership ID is resolved from WorkOS API using user's `workos_id` + configured org ID, cached locally
- [ ] Budget category resources are registered in WorkOS when shared
- [ ] Access checks use `permission_slug` (e.g., `budget_category:view`, `budget_category:edit`) instead of relation strings
- [ ] MCP server supports both session auth and OAuth 2.1 via WorkOS Connect
- [ ] All existing FGA tests updated and passing with new Http::fake patterns
- [ ] All 153+ existing tests continue to pass
- [ ] Dashboard setup documented (resource types, roles, permissions)

## Scope Boundaries

### In Scope

- Rewrite `app/Services/WorkOS/FGAService.php` to use new API
- Rewrite `app/Services/WorkOS/FGASchema.php` to document dashboard config instead of API schema
- Update `app/Http/Middleware/CheckBudgetAccess.php` for new check API
- Update `app/Livewire/Sharing/ShareBudgetCategory.php` to create FGA resources + role assignments
- Update `app/Livewire/Sharing/ManageSharing.php` to remove role assignments
- Update `app/Livewire/Budget/AllocationEditor.php` for new check API
- Add WorkOS Connect OAuth support to MCP server (`routes/ai.php`)
- Add `organization_id` to WorkOS config
- Update `tests/Feature/FGAServiceTest.php`, `BudgetAccessTest.php`, `SharingFlowTest.php`
- Add `docs/fga-dashboard-setup.md` with WorkOS Dashboard configuration steps

### Out of Scope

- Changing the sharing UI flow or UX
- Adding new FGA resource types beyond what Phase 7 defined
- WorkOS Dashboard automation (manual setup documented, not automated)
- Organization management UI (single family org, configured once)
- Migrating the local `organization_memberships` table to WorkOS (it stays as-is, unused)

## Execution Plan

### Dependency Graph

```
Single phase — sequential implementation
```

### Execution Steps

**Strategy**: Single phase, single session. The scope is a rewrite of existing files, not new feature development.

```bash
/ideation:execute-spec docs/ideation/cashbird-fga-rewrite/spec.md
```

---

_This contract was generated from a brain dump and research into the current WorkOS FGA API._
