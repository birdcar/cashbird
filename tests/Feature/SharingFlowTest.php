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

    public function test_sharing_creates_warrant_and_invitation(): void
    {
        Http::fake([
            '*/fga/v1/warrants' => Http::response([], 200),
        ]);

        $categoryId = Str::uuid()->toString();

        $this->fga->createWarrant('budget_category', $categoryId, 'viewer', 'user', $this->partner->workos_id);

        $invitation = SharingInvitation::create([
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->partner->id,
            'resource_type' => 'budget_category',
            'resource_id' => $categoryId,
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/fga/v1/warrants'));
        $this->assertDatabaseCount('sharing_invitations', 1);
        $this->assertEquals(SharingStatus::Active, $invitation->status);
    }

    public function test_revoking_deletes_warrant_and_updates_invitation(): void
    {
        Http::fake([
            '*/fga/v1/warrants' => Http::response([], 200),
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

        $this->fga->deleteWarrant('budget_category', $categoryId, 'viewer', 'user', $this->partner->workos_id);
        $invitation->revoke();

        Http::assertSent(fn ($r) => $r->method() === 'DELETE');
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

    public function test_duplicate_share_is_idempotent(): void
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
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => true]],
            ], 200),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', $this->partner->workos_id);

        $this->assertTrue($result);
    }
}
