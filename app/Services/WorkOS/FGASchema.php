<?php

declare(strict_types=1);

namespace App\Services\WorkOS;

/**
 * Reference for WorkOS Dashboard FGA configuration.
 *
 * Resource types, roles, and permissions are configured in the WorkOS Dashboard,
 * not via API. These constants serve as the source of truth for what must exist
 * in the dashboard for Cashbird's FGA to work correctly.
 *
 * @see docs/fga-dashboard-setup.md for setup instructions
 */
class FGASchema
{
    public const string RESOURCE_TYPE_BUDGET_CATEGORY = 'budget_category';

    public const string RESOURCE_TYPE_REPORT = 'report';

    public const string ROLE_OWNER = 'owner';

    public const string ROLE_EDITOR = 'editor';

    public const string ROLE_VIEWER = 'viewer';

    public const string PERMISSION_BUDGET_VIEW = 'budget_category:view';

    public const string PERMISSION_BUDGET_EDIT = 'budget_category:edit';

    public const string PERMISSION_REPORT_VIEW = 'report:view';
}
