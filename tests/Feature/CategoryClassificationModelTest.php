<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BudgetCategory;
use App\Enums\DebtStatus;
use App\Enums\SavingsStage;
use App\Models\Category;
use App\Models\CategoryClassification;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryClassificationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_record(): void
    {
        $classification = CategoryClassification::factory()->create();

        $this->assertDatabaseHas('category_classifications', ['id' => $classification->id]);
        $this->assertInstanceOf(BudgetCategory::class, $classification->classification);
    }

    public function test_belongs_to_user_and_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $classification = CategoryClassification::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($classification->user->is($user));
        $this->assertTrue($classification->category->is($category));
    }

    public function test_unique_constraint_on_user_and_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        CategoryClassification::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->expectException(QueryException::class);
        CategoryClassification::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_user_has_many_classifications(): void
    {
        $user = User::factory()->create();
        CategoryClassification::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->categoryClassifications);
    }

    public function test_need_want_savings_factory_states(): void
    {
        $need = CategoryClassification::factory()->need()->create();
        $want = CategoryClassification::factory()->want()->create();
        $savings = CategoryClassification::factory()->savings()->create();

        $this->assertEquals(BudgetCategory::Need, $need->classification);
        $this->assertEquals(BudgetCategory::Want, $want->classification);
        $this->assertEquals(BudgetCategory::Savings, $savings->classification);
    }

    public function test_user_override_factory_state(): void
    {
        $override = CategoryClassification::factory()->userOverride()->create();

        $this->assertFalse($override->is_ai_assigned);
    }

    // User::currentSavingsStage() tests

    public function test_savings_stage_starter_emergency_fund(): void
    {
        $user = User::factory()->create();
        Debt::factory()->create(['user_id' => $user->id, 'status' => DebtStatus::Active]);

        $this->assertEquals(SavingsStage::StarterEmergencyFund, $user->currentSavingsStage());
    }

    public function test_savings_stage_debt_payoff(): void
    {
        $user = User::factory()->create();
        Debt::factory()->create(['user_id' => $user->id, 'status' => DebtStatus::Active]);
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 100000,
        ]);

        $this->assertEquals(SavingsStage::DebtPayoff, $user->currentSavingsStage());
    }

    public function test_savings_stage_full_emergency_fund(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 50000,
        ]);

        $this->assertEquals(SavingsStage::FullEmergencyFund, $user->currentSavingsStage());
    }

    public function test_savings_stage_named_goals(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 999999999,
        ]);

        $this->assertEquals(SavingsStage::NamedGoals, $user->currentSavingsStage());
    }
}
