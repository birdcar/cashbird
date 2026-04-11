<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\WorkOS\FGAService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FGAServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FGAService $fga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fga = app(FGAService::class);
    }

    public function test_get_organization_membership_id_resolves_and_caches(): void
    {
        Http::fake([
            '*/user_management/organization_memberships*' => Http::response([
                'data' => [['id' => 'om_01ABC']],
            ], 200),
        ]);

        $id = $this->fga->getOrganizationMembershipId('user_01XYZ');
        $this->assertEquals('om_01ABC', $id);

        // Second call should use cache — no additional HTTP
        $id2 = $this->fga->getOrganizationMembershipId('user_01XYZ');
        $this->assertEquals('om_01ABC', $id2);

        Http::assertSentCount(1);
    }

    public function test_get_organization_membership_id_returns_null_on_failure(): void
    {
        Http::fake([
            '*/user_management/organization_memberships*' => Http::response([], 500),
        ]);

        $id = $this->fga->getOrganizationMembershipId('user_01XYZ');
        $this->assertNull($id);
    }

    public function test_create_resource_sends_correct_request(): void
    {
        Http::fake([
            '*/authorization/resources' => Http::response([
                'id' => 'authz_resource_01ABC',
                'resource_type_slug' => 'budget_category',
                'external_id' => 'cat-123',
            ], 200),
        ]);

        $resourceId = $this->fga->createResource('budget_category', 'cat-123', 'Groceries');

        $this->assertEquals('authz_resource_01ABC', $resourceId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/authorization/resources')
                && $request->method() === 'POST'
                && $request['resource_type_slug'] === 'budget_category'
                && $request['external_id'] === 'cat-123'
                && $request['name'] === 'Groceries';
        });
    }

    public function test_assign_role_sends_correct_request(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/role_assignments' => Http::response([
                'id' => 'role_assignment_01ABC',
            ], 200),
        ]);

        $this->fga->assignRole('om_01ABC', 'viewer', 'authz_resource_01XYZ');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/organization_memberships/om_01ABC/role_assignments')
                && $request->method() === 'POST'
                && $request['role_slug'] === 'viewer'
                && $request['resource_id'] === 'authz_resource_01XYZ';
        });
    }

    public function test_remove_role_sends_delete_request(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/role_assignments' => Http::response([], 200),
        ]);

        $this->fga->removeRole('om_01ABC', 'viewer', 'authz_resource_01XYZ');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/organization_memberships/om_01ABC/role_assignments');
        });
    }

    public function test_check_returns_true_when_authorized(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => true,
            ], 200),
        ]);

        $result = $this->fga->check('om_01ABC', 'budget_category:view', 'authz_resource_01XYZ');

        $this->assertTrue($result);
    }

    public function test_check_returns_false_when_unauthorized(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => false,
            ], 200),
        ]);

        $result = $this->fga->check('om_01ABC', 'budget_category:edit', 'authz_resource_01XYZ');

        $this->assertFalse($result);
    }

    public function test_check_returns_false_on_api_failure(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([], 500),
        ]);

        $result = $this->fga->check('om_01ABC', 'budget_category:view', 'authz_resource_01XYZ');

        $this->assertFalse($result);
    }

    public function test_check_caches_result(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => true,
            ], 200),
        ]);

        $this->fga->check('om_01ABC', 'budget_category:view', 'authz_resource_01XYZ');
        $this->fga->check('om_01ABC', 'budget_category:view', 'authz_resource_01XYZ');

        Http::assertSentCount(1);
    }

    public function test_assign_role_invalidates_cache(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => true,
            ], 200),
            '*/authorization/organization_memberships/*/role_assignments' => Http::response([
                'id' => 'role_assignment_01ABC',
            ], 200),
        ]);

        $this->fga->check('om_01ABC', 'budget_category:view', 'authz_resource_01XYZ');
        $this->fga->assignRole('om_01ABC', 'editor', 'authz_resource_01XYZ');
        $this->fga->check('om_01ABC', 'budget_category:view', 'authz_resource_01XYZ');

        // 1 check + 1 assign + 1 check = 3 HTTP requests
        Http::assertSentCount(3);
    }

    public function test_list_role_assignments_returns_collection(): void
    {
        Http::fake([
            '*/authorization/role_assignments*' => Http::response([
                'data' => [
                    ['id' => 'ra_01', 'role' => ['slug' => 'viewer']],
                ],
            ], 200),
        ]);

        $assignments = $this->fga->listRoleAssignments('authz_resource_01XYZ');

        $this->assertCount(1, $assignments);
        $this->assertEquals('viewer', $assignments[0]['role']['slug']);
    }
}
