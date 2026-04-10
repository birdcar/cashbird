# Implementation Spec: Cashbird - Phase 7: Family Access & WorkOS FGA

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Implement multi-user access control using WorkOS Fine-Grained Authorization (FGA). Each user has private accounts and budgets by default. Users can share specific budget categories with other users (e.g., household expenses shared between partners). The child user gets view-only access to shared items.

FGA uses a ReBAC (Relationship-Based Access Control) model via warrants: `budget_category:groceries#editor@user:partner_id`. The authorization schema defines resource types, relations, and inheritance rules.

All existing queries are already scoped by `user_id` (Phases 2-6). This phase adds a sharing layer on top — when a user views shared budget items, the system checks FGA warrants to determine access level (viewer, editor).

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=FGA`

**Playground**: Test suite with mocked WorkOS FGA API responses. Integration testing with real FGA requires WorkOS sandbox credentials.

**Why this approach**: FGA integration is API-call heavy but the logic is straightforward warrant CRUD + check. Tests with HTTP fakes validate the authorization flow without hitting WorkOS on every run.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `app/Services/WorkOS/FGAService.php` | FGA warrant management (create, delete, check) |
| `app/Services/WorkOS/FGASchema.php` | Authorization schema definition |
| `app/Http/Middleware/CheckBudgetAccess.php` | Middleware to verify FGA access on budget routes |
| `app/Livewire/Sharing/ShareBudgetCategory.php` | UI for sharing a budget category with another user |
| `app/Livewire/Sharing/SharedWithMe.php` | List of budget items shared with current user |
| `app/Livewire/Sharing/ManageSharing.php` | Manage all sharing settings for own budget items |
| `database/migrations/xxxx_create_sharing_invitations_table.php` | Local tracking of sharing invitations |
| `resources/views/livewire/sharing/` | Sharing UI views |
| `tests/Feature/FGAServiceTest.php` | FGA warrant CRUD tests |
| `tests/Feature/BudgetAccessTest.php` | Authorization check tests |
| `tests/Feature/SharingFlowTest.php` | End-to-end sharing flow tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `app/Livewire/Budget/BudgetOverview.php` | Show shared budget items alongside own items |
| `app/Livewire/Budget/AllocationEditor.php` | Respect FGA permissions (viewer can't edit) |
| `app/Livewire/Dashboard/SpendingBreakdown.php` | Include shared category data if authorized |
| `routes/web.php` | Add sharing routes, apply `CheckBudgetAccess` middleware to budget routes |
| `config/workos.php` | Add FGA configuration (warrant API endpoint) |
| `.env.example` | Add WORKOS_FGA_* environment variables if any |

## Implementation Details

### FGA Authorization Schema

**Overview**: Define the authorization model for Cashbird in WorkOS FGA.

```
// Resource types and relations

type user

type budget_category
  relations
    define owner: [user]
    define editor: [user] or owner
    define viewer: [user] or editor

type budget_period
  relations
    define owner: [user]
    define viewer: [user] or owner

type report
  relations
    define owner: [user]
    define viewer: [user] or owner
```

**Access model**:
- `owner`: Full control. The user who created the budget/category. Implicit.
- `editor`: Can view allocations, spending, and modify shared budget amounts. Granted via explicit warrant.
- `viewer`: Can see allocations and spending but cannot modify. Granted via explicit warrant.
- Inheritance: `editor` implies `viewer`. `owner` implies `editor` and `viewer`.

**Implementation steps**:
1. Define schema in `FGASchema` class as a structured array
2. On first deploy or via artisan command: push schema to WorkOS FGA API
3. When a new budget category is created: automatically create `owner` warrant for the user

### FGA Service

**Overview**: Wrapper around WorkOS PHP SDK for FGA operations.

```php
class FGAService
{
    public function createWarrant(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId): void;
    public function deleteWarrant(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId): void;
    public function check(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId): bool;
    public function listWarrants(string $resourceType, string $resourceId): Collection;
    public function batchCheck(array $checks): array; // [{resource, relation, subject} => bool]
}
```

**Implementation steps**:
1. Use WorkOS PHP SDK's FGA methods (or direct HTTP if SDK method not available)
2. `createWarrant()`: POST to `/fga/v1/warrants` with warrant tuple
3. `deleteWarrant()`: DELETE warrant
4. `check()`: POST to `/fga/v1/check` — returns `{authorized: true/false}`
5. `listWarrants()`: GET warrants for a resource
6. `batchCheck()`: Multiple check calls for dashboard rendering efficiency

**Key decisions**:
- Cache check results in Redis for 60 seconds to reduce FGA API calls on page loads
- Invalidate cache on warrant create/delete
- FGA is the source of truth; local cache is optimization only

**Feedback loop**:
- **Playground**: Create `tests/Feature/FGAServiceTest.php` with HTTP::fake() for WorkOS API
- **Experiment**: Test warrant CRUD (create, verify exists, delete, verify gone), check authorization (positive and negative), batch check with mixed results, cache invalidation on warrant change
- **Check command**: `php artisan test --filter=FGAService`

### Budget Access Middleware

**Overview**: Middleware that checks FGA authorization before allowing access to budget resources.

```php
class CheckBudgetAccess
{
    public function handle(Request $request, Closure $next, string $relation = 'viewer'): Response
    {
        $categoryId = $request->route('category');
        $userId = auth()->id();

        // Owner always has access (check user_id on model)
        // Otherwise, check FGA
        if (!$this->isOwner($categoryId, $userId) && !$this->fga->check('budget_category', $categoryId, $relation, 'user', $userId)) {
            abort(403);
        }

        return $next($request);
    }
}
```

**Implementation steps**:
1. Create middleware that extracts resource from route parameter
2. Check ownership first (fast path, no API call)
3. If not owner, check FGA for requested relation
4. Apply to budget routes with appropriate relation (`viewer` for GET, `editor` for PUT/POST)

**Feedback loop**:
- **Playground**: Create `tests/Feature/BudgetAccessTest.php`
- **Experiment**: Owner accesses own budget (200), shared viewer accesses shared budget (200), shared viewer tries to edit (403), unshared user accesses (403), editor can modify shared budget (200)
- **Check command**: `php artisan test --filter=BudgetAccess`

### Sharing Flow

**Overview**: User shares a budget category with another Cashbird user. The recipient sees shared items in their budget view.

**Flow**:
1. Owner goes to budget category → clicks "Share"
2. `ShareBudgetCategory` component: select user (by email/name search among Cashbird users), select role (viewer/editor)
3. On submit: create FGA warrant + local tracking record
4. Recipient: `SharedWithMe` component shows shared categories on their dashboard
5. Shared category data appears in recipient's budget overview (marked as "shared")

**Data model for local tracking**:
```sql
CREATE TABLE sharing_invitations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    from_user_id UUID NOT NULL REFERENCES users(id),
    to_user_id UUID NOT NULL REFERENCES users(id),
    resource_type VARCHAR(50) NOT NULL, -- budget_category, report
    resource_id UUID NOT NULL,
    relation VARCHAR(20) NOT NULL, -- viewer, editor
    status VARCHAR(20) DEFAULT 'active', -- active, revoked
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(from_user_id, to_user_id, resource_type, resource_id)
);
```

**Why local tracking?** FGA warrants don't store metadata (who shared, when, context). The local table provides audit trail and UI data.

**Implementation steps**:
1. `ShareBudgetCategory` Livewire component with user search (query WorkOS User Management or local users table)
2. On share: create FGA warrant via `FGAService::createWarrant()`, insert `sharing_invitations` row
3. On revoke: delete FGA warrant, update invitation status to `revoked`
4. `SharedWithMe`: query `sharing_invitations` where `to_user_id = auth()->id()`
5. Modify `BudgetOverview` to include shared categories (separate section, visually distinct)

**Feedback loop**:
- **Playground**: Create `tests/Feature/SharingFlowTest.php`
- **Experiment**: Share category with partner (warrant created, invitation stored), partner can view shared category, partner cannot view unshared categories, revoke sharing (warrant deleted, access removed), re-share after revoke
- **Check command**: `php artisan test --filter=SharingFlow`

### Modified Budget Views

**Overview**: Update existing budget components to handle shared data.

**Changes**:
1. `BudgetOverview`: After loading own allocations, query `sharing_invitations` for shared categories. For each, batch-check FGA access, then load the sharing user's allocation data for those categories. Display in "Shared with you" section.
2. `AllocationEditor`: Check if current user is `owner` or `editor` before rendering edit controls. Viewers see read-only display.
3. `SpendingBreakdown`: Optionally include shared category spending for combined household view.

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/FGAServiceTest.php` | Warrant CRUD, check, batch check, caching |
| `tests/Feature/BudgetAccessTest.php` | Middleware authorization for owner, editor, viewer, unauthorized |
| `tests/Feature/SharingFlowTest.php` | End-to-end: share, access, edit, revoke |

**Key test cases**:
- FGA warrant created on share
- FGA warrant deleted on revoke
- Owner bypasses FGA check (no API call needed)
- Viewer can see but not edit shared budget
- Editor can see and edit shared budget
- Unauthorized user gets 403
- Cache invalidates on warrant change
- Shared categories appear in recipient's budget overview
- Revoking access removes category from recipient's view
- Cannot share with yourself
- Sharing the same category twice (same user, same relation) is idempotent

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| FGAService | WorkOS FGA API down | Service outage | Cannot verify sharing permissions | Fall back to deny (fail-closed); show "sharing temporarily unavailable" for shared items only — own items always accessible |
| FGAService | Stale cache | Cache not invalidated after warrant change | User sees shared item they shouldn't (or can't see one they should) | 60s TTL as safety net; explicit invalidation on warrant CRUD |
| CheckBudgetAccess | High latency on batch check | Many shared categories | Dashboard loads slowly | Batch checks in single API call; Redis cache; lazy-load shared section |
| SharingFlow | Warrant created but invitation insert fails | Database error after FGA API call | FGA says authorized but no UI tracking | Wrap in transaction; if insert fails, delete warrant and retry |
| SharingFlow | User deleted but warrants remain | User account removed | Orphaned warrants in FGA | Clean up warrants on user deletion event |

## Validation Commands

```bash
# Run migrations
php artisan migrate

# Run all Phase 7 tests
php artisan test --filter=FGA
php artisan test --filter=BudgetAccess
php artisan test --filter=SharingFlow

# Verify FGA schema (requires WorkOS credentials)
# php artisan tinker --execute="app(FGAService::class)->listWarrants('budget_category', 'test');"

# Manual: share a budget category
# Navigate to Budget → Category → Share → Select user → Confirm
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
