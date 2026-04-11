# WorkOS FGA Dashboard Setup

This guide documents the WorkOS Dashboard configuration required for Cashbird's Fine-Grained Authorization.

## Prerequisites

- WorkOS account with FGA enabled
- A single Organization created for the family (note the `org_id`)
- All family members added as Organization Members

## 1. Resource Types

Navigate to **Dashboard > Authorization > Resource Types** and create:

### `budget_category`

Budget categories that can be shared between family members.

### `report`

Monthly financial reports (for future sharing support).

## 2. Roles

Under each resource type, create the following roles:

### `budget_category` roles

| Role | Description |
|------|-------------|
| `owner` | Full control — implicit for the user who created the budget |
| `editor` | Can view and modify shared budget allocations |
| `viewer` | Can view but not modify shared allocations |

### `report` roles

| Role | Description |
|------|-------------|
| `owner` | Full control |
| `viewer` | Can view the report |

## 3. Permissions

Create permissions and assign them to roles:

### `budget_category` permissions

| Permission | Assigned to roles |
|------------|-------------------|
| `budget_category:view` | `viewer`, `editor`, `owner` |
| `budget_category:edit` | `editor`, `owner` |

### `report` permissions

| Permission | Assigned to roles |
|------------|-------------------|
| `report:view` | `viewer`, `owner` |

## 4. Environment Configuration

Add to your `.env`:

```
WORKOS_ORGANIZATION_ID=org_01YOUR_ORG_ID_HERE
```

## 5. Enable CIMD (for MCP OAuth)

Navigate to **Dashboard > Connect > Configuration** and enable **Client ID Metadata Document (CIMD)**. This allows MCP clients to authenticate via OAuth 2.1 without pre-registration.

## How It Works

1. When a user shares a budget category, Cashbird creates a **resource** in WorkOS FGA via the API (`POST /authorization/resources`)
2. Cashbird then creates a **role assignment** linking the recipient's organization membership to a role on that resource (`POST /authorization/organization_memberships/:id/role_assignments`)
3. Access checks use the permission system (`POST /authorization/organization_memberships/:id/check`) — e.g., checking `budget_category:view` on a specific resource
4. The organization membership ID is resolved from the user's `workos_id` via the User Management API and cached for 24 hours

## Reference

Constants for resource types, roles, and permissions are defined in `app/Services/WorkOS/FGASchema.php` for use throughout the codebase.
