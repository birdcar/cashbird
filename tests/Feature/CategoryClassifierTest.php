<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BudgetCategory;
use App\Models\Category;
use App\Models\CategoryClassification;
use App\Models\User;
use App\Services\Budget\CategoryClassifier;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryClassifierTest extends TestCase
{
    use RefreshDatabase;

    private CategoryClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = app(CategoryClassifier::class);
        $this->seed(CategorySeeder::class);
    }

    public function test_classifies_housing_as_need(): void
    {
        $user = User::factory()->create();
        $rent = Category::where('name', 'Rent/Mortgage')->first();

        $classification = $this->classifier->getClassification($user->id, $rent->id);

        $this->assertEquals(BudgetCategory::Need, $classification);
    }

    public function test_classifies_restaurants_as_want(): void
    {
        $user = User::factory()->create();
        $dining = Category::where('name', 'Restaurants')->first();

        $classification = $this->classifier->getClassification($user->id, $dining->id);

        $this->assertEquals(BudgetCategory::Want, $classification);
    }

    public function test_classifies_savings_transfer_as_savings(): void
    {
        $user = User::factory()->create();
        $savings = Category::where('name', 'Transfer to Savings')->first();

        $classification = $this->classifier->getClassification($user->id, $savings->id);

        $this->assertEquals(BudgetCategory::Savings, $classification);
    }

    public function test_classifies_groceries_as_need(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();

        $classification = $this->classifier->getClassification($user->id, $groceries->id);

        $this->assertEquals(BudgetCategory::Need, $classification);
    }

    public function test_user_override_takes_precedence(): void
    {
        $user = User::factory()->create();
        $dining = Category::where('name', 'Restaurants')->first();

        $this->classifier->overrideClassification($user->id, $dining->id, BudgetCategory::Need);

        $classification = $this->classifier->getClassification($user->id, $dining->id);
        $this->assertEquals(BudgetCategory::Need, $classification);

        $record = CategoryClassification::where('user_id', $user->id)
            ->where('category_id', $dining->id)
            ->first();
        $this->assertFalse($record->is_ai_assigned);
    }

    public function test_classify_for_user_creates_records(): void
    {
        $user = User::factory()->create();

        $classifications = $this->classifier->classifyForUser($user->id);

        $this->assertNotEmpty($classifications);
        $this->assertGreaterThan(0, CategoryClassification::where('user_id', $user->id)->count());
    }

    public function test_classify_for_user_respects_existing_overrides(): void
    {
        $user = User::factory()->create();
        $dining = Category::where('name', 'Restaurants')->first();

        $this->classifier->overrideClassification($user->id, $dining->id, BudgetCategory::Need);

        $classifications = $this->classifier->classifyForUser($user->id);

        $this->assertEquals(BudgetCategory::Need, $classifications[$dining->id]);
    }

    public function test_unknown_category_defaults_to_want(): void
    {
        $user = User::factory()->create();

        $classification = $this->classifier->getClassification($user->id, 'nonexistent-uuid');

        $this->assertEquals(BudgetCategory::Want, $classification);
    }
}
