<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SharingRelation;
use App\Enums\SharingStatus;
use App\Models\SharingInvitation;
use App\Models\User;
use App\Services\WorkOS\FGAService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharingFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private User $partner;

    private FGAService $fga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['workos_id' => 'user_owner']);
        $this->partner = User::factory()->create(['workos_id' => 'user_partner']);
        $this->fga = app(FGAService::class);
    }

    public function test_sharing_creates_resource_and_role_assignment(): void
    {
        Http::fake([
            '*/authorization/resources' => Http::response([
                'id' => 'authz_resource_01ABC',
                'resource_type_slug' => 'budget_category',
            ], 200),
            '*/authorization/organization_memberships/*/role_assignments' => Http::response([
                'id' => 'role_assignment_01ABC',
            ], 200),
        ]);

        $categoryId = Str::uuid()->toString();

        $resourceId = $this->fga->createResource('budget_category', $categoryId, 'Groceries');
        $this->fga->assignRole('om_partner', 'viewer', $resourceId);

        $invitation = SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/authorization/resources') && $r->method() === 'POST');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/role_assignments') && $r->method() === 'POST');
        $this->assertDatabaseCount('sharing_invitations', 1);
        $this->assertEquals(SharingStatus::Active, $invitation->status);
    }

    public function test_revoking_removes_role_assignment(): void
    {
        Http::fake([
            '*/user_management/organization_memberships*' => Http::response([
                'data' => [['id' => 'om_partner']],
            ], 200),
            '*/authorization/organization_memberships/*/role_assignments' => Http::response([], 200),
        ]);

        $categoryId = Str::uuid()->toString();

        $invitation = SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ]);

        $membershipId = $this->fga->getOrganizationMembershipId($this->partner->workos_id);
        $this->fga->removeRole($membershipId, 'viewer', $categoryId);
        $invitation->revoke();

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/role_assignments'));
        $this->assertEquals(SharingStatus::Revoked, $invitation->fresh()->status);
    }

    public function test_self_share_is_stored_but_should_be_prevented_by_ui(): void
    {
        $categoryId = Str::uuid()->toString();

        $invitation = SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->owner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ]);

        $this->assertDatabaseCount('sharing_invitations', 1);
        $this->assertEquals($this->owner->id, $invitation->from_user_id);
        $this->assertEquals($this->owner->id, $invitation->to_user_id);
    }

    public function test_duplicate_share_throws_constraint(): void
    {
        $categoryId = Str::uuid()->toString();

        SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Editor,
            'status' => SharingStatus::Active,
        ]);
    }

    public function test_shared_with_me_returns_active_invitations(): void
    {
        $categoryId = Str::uuid()->toString();

        SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ]);

        SharingInvitation::factory()->revoked()->create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
        ]);

        $active = SharingInvitation::where('to_user_id', $this->partner->id)
            ->active()
            ->get();

        $this->assertCount(1, $active);
        $this->assertEquals($categoryId, $active->first()->resource_id);
    }

    public function test_partner_can_check_access_via_fga(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => true,
            ], 200),
        ]);

        $result = $this->fga->check('om_partner', 'budget_category:view', 'authz_resource_01XYZ');

        $this->assertTrue($result);
    }
}
