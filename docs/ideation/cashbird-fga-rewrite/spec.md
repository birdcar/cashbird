# Implementation Spec: Cashbird FGA Rewrite

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Rewrite the FGA layer from the deprecated Zanzibar-style warrant API (`/fga/v1/warrants`, `/fga/v1/check`) to the current WorkOS resource-based authorization API (`/authorization/resources`, `/authorization/organization_memberships/:id/role_assignments`, `/authorization/organization_memberships/:id/check`).

The key architectural shift: the old API used `user:workos_id` as subjects in warrant tuples. The new API uses `organization_membership_id` as the subject for all authorization operations. Cashbird will operate under a single family organization. Each user's `organization_membership_id` is resolved via the WorkOS User Management API (`GET /user_management/organization_memberships`) using their `workos_id`, then cached.

Resource types, roles, and permissions are configured in the WorkOS Dashboard — not via API. The spec includes dashboard setup documentation.

Additionally, the MCP server will support both session auth (browser) and OAuth 2.1 via WorkOS Connect (external MCP clients like Claude Desktop).

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=FGA`

**Playground**: Test suite with Http::fake() for WorkOS API calls.

**Why this approach**: FGA integration is HTTP-call-heavy but testable with mocked responses. The rewrite touches the same files as Phase 7, so the existing test structure carries over.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `docs/fga-dashboard-setup.md` | WorkOS Dashboard configuration guide (resource types, roles, permissions) |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `app/Services/WorkOS/FGAService.php` | Complete rewrite: new API endpoints, org membership resolution, resource management |
| `app/Services/WorkOS/FGASchema.php` | Replace API schema push with dashboard configuration reference |
| `app/Http/Middleware/CheckBudgetAccess.php` | Use permission_slug-based check instead of relation |
| `app/Livewire/Sharing/ShareBudgetCategory.php` | Create FGA resource + role assignment instead of warrant |
| `app/Livewire/Sharing/ManageSharing.php` | Remove role assignment instead of deleting warrant |
| `app/Livewire/Budget/AllocationEditor.php` | Use permission-based check in canEdit() |
| `config/workos.php` | Add `organization_id` config |
| `routes/ai.php` | Add OAuth 2.1 support via WorkOS Connect alongside session auth |
| `tests/Feature/FGAServiceTest.php` | Rewrite all Http::fake patterns for new endpoints |
| `tests/Feature/BudgetAccessTest.php` | Update check assertions for new API |
| `tests/Feature/SharingFlowTest.php` | Update sharing assertions for new API |

### Deleted Files

| File Path | Reason |
|-----------|--------|
| None | All files are modified in-place |

## Implementation Details

### WorkOS Dashboard Configuration

**Overview**: Document the resource types, roles, and permissions that must be configured in the WorkOS Dashboard before the code works.

**Dashboard setup** (documented in `docs/fga-dashboard-setup.md`):

1. **Resource Types** (create in Dashboard → Authorization → Resource Types):
   - `budget_category` — Budget categories that can be shared between users
   - `report` — Monthly financial reports

2. **Roles** (create under each resource type):
   - `budget_category`:
     - `owner` — Full control (implicit for creator)
     - `editor` — Can view and modify shared allocations
     - `viewer` — Can view but not modify
   - `report`:
     - `owner` — Full control
     - `viewer` — Can view report

3. **Permissions** (create under each role):
   - `budget_category:view` — assigned to viewer, editor, owner roles
   - `budget_category:edit` — assigned to editor, owner roles
   - `report:view` — assigned to viewer, owner roles

**Implementation steps**:
1. Write `docs/fga-dashboard-setup.md` with step-by-step Dashboard instructions
2. Update `FGASchema.php` to serve as a reference document (not API caller) — define the expected types/roles/permissions as PHP constants

### FGAService Rewrite

**Pattern to follow**: `app/Services/Debt/DebtSynchronizer.php` (service with constructor injection, Http calls)

**Overview**: Complete rewrite of FGAService to use the current `/authorization/*` endpoints.

```php
class FGAService
{
    // Resolve org membership ID from user's workos_id + org
    public function getOrganizationMembershipId(string $workosUserId): string;

    // Register a resource in WorkOS FGA
    public function createResource(string $resourceTypeSlug, string $externalId, string $name): string;
    public function deleteResource(string $resourceId): void;

    // Assign/remove roles on resources
    public function assignRole(string $orgMembershipId, string $roleSlug, string $resourceId): void;
    public function removeRole(string $orgMembershipId, string $roleSlug, string $resourceId): void;

    // Check access
    public function check(string $orgMembershipId, string $permissionSlug, string $resourceId): bool;

    // List role assignments for a resource
    public function listRoleAssignments(string $resourceId): Collection;
}
```

**Key decisions**:
- `getOrganizationMembershipId()` caches the result in Redis with a 24-hour TTL. The org membership ID doesn't change unless the user is removed from the org.
- `createResource()` uses `external_id` to map to Cashbird's internal category UUIDs — avoids storing WorkOS resource IDs locally.
- `check()` uses `permission_slug` (e.g., `budget_category:view`) instead of relation strings — more explicit and matches Dashboard config.
- All HTTP calls use `Http::withToken()` for testability with `Http::fake()`.
- WorkOS API base URL: `https://api.workos.com` (same as existing config).

**API endpoint mapping**:

| Old (deprecated) | New (current) |
|---|---|
| `POST /fga/v1/warrants` | `POST /authorization/resources` + `POST /authorization/organization_memberships/:id/role_assignments` |
| `DELETE /fga/v1/warrants` | `DELETE /authorization/organization_memberships/:id/role_assignments` |
| `POST /fga/v1/check` | `POST /authorization/organization_memberships/:id/check` |
| `GET /fga/v1/warrants` | `GET /authorization/role_assignments` |

**Implementation steps**:
1. Add `organization_id` to `config/workos.php` under `fga` key
2. Implement `getOrganizationMembershipId()` — `GET /user_management/organization_memberships?user_id={workos_id}&organization_id={org_id}`, cache result
3. Implement `createResource()` — `POST /authorization/resources` with `resource_type_slug`, `external_id`, `name`, `organization_id`
4. Implement `deleteResource()` — `DELETE /authorization/resources/:id`
5. Implement `assignRole()` — `POST /authorization/organization_memberships/:id/role_assignments` with `role_slug` + `resource_id`
6. Implement `removeRole()` — `DELETE /authorization/organization_memberships/:id/role_assignments` with `role_slug` + `resource_id`
7. Implement `check()` — `POST /authorization/organization_memberships/:id/check` with `permission_slug` + `resource_id`
8. Keep generation-counter cache invalidation for check results

**Feedback loop**:
- **Playground**: `tests/Feature/FGAServiceTest.php` with Http::fake for all `/authorization/*` endpoints
- **Experiment**: Create resource, assign role, check access (positive), remove role, check access (negative), verify cache invalidation
- **Check command**: `php artisan test --filter=FGAService`

### Caller Updates

**Overview**: Update all callers to use the new FGAService methods.

**ShareBudgetCategory changes**:
1. In `share()`: call `$fga->createResource('budget_category', $categoryId, $categoryName)` to register the resource
2. Then call `$fga->assignRole($recipientMembershipId, 'viewer'/'editor', $resourceId)`
3. Use `$fga->getOrganizationMembershipId($recipient->workos_id)` to resolve the membership ID

**ManageSharing changes**:
1. In `revoke()`: call `$fga->removeRole($recipientMembershipId, $relation, $resourceId)` instead of `deleteWarrant`

**CheckBudgetAccess changes**:
1. Use `$fga->check($membershipId, 'budget_category:view', $categoryId)` for GET routes
2. Use `$fga->check($membershipId, 'budget_category:edit', $categoryId)` for POST/PUT routes
3. Resolve membership ID via `$fga->getOrganizationMembershipId($user->workos_id)`

**AllocationEditor changes**:
1. `canEdit()`: call `$fga->check($membershipId, 'budget_category:edit', $categoryId)` instead of `check('budget_category', ..., 'editor', ...)`

**Implementation steps**:
1. Update ShareBudgetCategory — resource creation + role assignment
2. Update ManageSharing — role removal
3. Update CheckBudgetAccess — permission-based check
4. Update AllocationEditor — permission-based canEdit

**Feedback loop**:
- **Check command**: `php artisan test --filter=Sharing`

### MCP OAuth via WorkOS Connect

**Overview**: Add OAuth 2.1 support for external MCP clients via WorkOS Connect, keeping session auth for browser access.

**Implementation steps**:
1. Add `/.well-known/oauth-protected-resource` route returning the authorization server URL (AuthKit subdomain)
2. In `routes/ai.php`, configure MCP with both middleware options: session auth for browser, Bearer token validation for external clients
3. Add JWT verification middleware that validates tokens issued by AuthKit using the JWKS endpoint
4. Enable CIMD in WorkOS Dashboard (documented in setup guide)

**Key decisions**:
- Use `firebase/php-jwt` or `lcobucci/jwt` for JWT verification (check if already in vendor)
- The `sub` claim in the JWT maps to `workos_id` — use this to resolve the authenticated user
- MCP tool handlers already use `$request->user()` which works with both auth methods

**Feedback loop**:
- **Playground**: Start MCP server, test with curl + Bearer token
- **Check command**: `php artisan test --filter=McpTools`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/FGAServiceTest.php` | Resource CRUD, role assignment, access check, membership resolution, caching |
| `tests/Feature/BudgetAccessTest.php` | Middleware permission checks for owner, editor, viewer, unauthorized |
| `tests/Feature/SharingFlowTest.php` | End-to-end: create resource, assign role, check access, remove role |

**Key test cases**:
- `createResource` sends POST to `/authorization/resources` with correct body
- `assignRole` sends POST to `/authorization/organization_memberships/:id/role_assignments`
- `check` returns true when `authorized: true` in response
- `check` returns false when `authorized: false`
- `check` caches result and cache is invalidated on role change
- `getOrganizationMembershipId` caches the resolved ID
- `removeRole` sends DELETE to correct endpoint
- Sharing flow: resource created, role assigned, access granted, role removed, access denied

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| FGAService | WorkOS API down | Service outage | Cannot create resources or check access | Fail-closed (deny access); own data always accessible via user_id scope |
| FGAService | Org membership not found | User not in the configured org | Cannot resolve membership ID | Return null, deny FGA access, log warning |
| FGAService | Stale membership cache | User removed from org, cache not expired | Access granted to removed user | 24h TTL; explicit invalidation on user deletion event |
| MCP OAuth | Invalid JWT | Expired or tampered token | 401 unauthorized | Standard JWT validation with JWKS rotation |
| ShareBudgetCategory | Resource already exists | Category shared before, then revoked, then re-shared | Duplicate resource in WorkOS | Use `external_id` for idempotent resource creation; check existence first |

## Validation Commands

```bash
# Run all FGA tests
php artisan test --filter=FGA
php artisan test --filter=BudgetAccess
php artisan test --filter=SharingFlow
php artisan test --filter=McpTools

# Run full suite
php artisan test --compact

# Lint
vendor/bin/pint --dirty --format agent
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
