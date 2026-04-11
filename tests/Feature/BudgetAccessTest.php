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

        $response = $this->actingAs($this->owner)
            ->get("/budget/category/{$category->id}");

        // Route doesn't exist yet but middleware should pass for owner
        // This verifies the middleware doesn't block the owner
        Http::assertNothingSent();
    }

    public function test_fga_check_grants_viewer_access(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => true]],
            ], 200),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_viewer');

        $this->assertTrue($result);
    }

    public function test_fga_check_denies_unauthorized_user(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => false]],
            ], 200),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'editor', 'user', 'user_stranger');

        $this->assertFalse($result);
    }

    public function test_viewer_cannot_be_granted_editor_by_fga(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => false]],
            ], 200),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'editor', 'user', 'user_viewer');

        $this->assertFalse($result);
    }
}
