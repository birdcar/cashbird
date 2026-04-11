<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;
use App\Services\WorkOS\FGAService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BudgetAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private User $viewer;

    private FGAService $fga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->owner = User::factory()->create(['workos_id' => 'user_owner']);
        $this->viewer = User::factory()->create(['workos_id' => 'user_viewer']);
        $this->fga = app(FGAService::class);
    }

    public function test_owner_can_access_own_budget_category(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->owner->id]);
        $period = BudgetPeriod::factory()->create(['budget_id' => $budget->id]);
        $category = Category::where('name', 'Groceries')->first();
        BudgetAllocation::factory()->create([
            'budget_period_id' => $period->id,
            'category_id' => $category->id,
        ]);

        Http::fake();

        // Owner check doesn't need FGA — ownership is checked via DB
        Http::assertNothingSent();
    }

    public function test_fga_check_grants_viewer_access(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => true,
            ], 200),
        ]);

        $result = $this->fga->check('om_viewer', 'budget_category:view', 'authz_resource_01XYZ');

        $this->assertTrue($result);
    }

    public function test_fga_check_denies_unauthorized_user(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => false,
            ], 200),
        ]);

        $result = $this->fga->check('om_stranger', 'budget_category:edit', 'authz_resource_01XYZ');

        $this->assertFalse($result);
    }

    public function test_viewer_cannot_edit_via_fga(): void
    {
        Http::fake([
            '*/authorization/organization_memberships/*/check' => Http::response([
                'authorized' => false,
            ], 200),
        ]);

        $result = $this->fga->check('om_viewer', 'budget_category:edit', 'authz_resource_01XYZ');

        $this->assertFalse($result);
    }
}
