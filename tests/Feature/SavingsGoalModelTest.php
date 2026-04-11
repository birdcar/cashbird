<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsGoalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_record(): void
    {
        $goal = SavingsGoal::factory()->create();

        $this->assertDatabaseHas('savings_goals', ['id' => $goal->id]);
        $this->assertInstanceOf(GoalStatus::class, $goal->status);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($goal->user->is($user));
    }

    public function test_user_has_many_savings_goals(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->savingsGoals);
    }

    public function test_mark_completed_sets_status_and_timestamp(): void
    {
        $goal = SavingsGoal::factory()->create();

        $goal->markCompleted();
        $goal->refresh();

        $this->assertEquals(GoalStatus::Completed, $goal->status);
        $this->assertNotNull($goal->completed_at);
    }

    public function test_emergency_fund_factory_state(): void
    {
        $goal = SavingsGoal::factory()->emergencyFund()->create();

        $this->assertEquals('Emergency Fund', $goal->name);
        $this->assertEquals(100000, $goal->target_amount);
        $this->assertTrue($goal->is_system);
        $this->assertEquals(0, $goal->priority);
    }

    public function test_completed_factory_state(): void
    {
        $goal = SavingsGoal::factory()->completed()->create(['target_amount' => 500000]);

        $this->assertEquals(GoalStatus::Completed, $goal->status);
        $this->assertEquals(500000, $goal->current_balance);
        $this->assertNotNull($goal->completed_at);
    }

    public function test_monetary_values_are_integers(): void
    {
        $goal = SavingsGoal::factory()->create([
            'target_amount' => 250000,
            'current_balance' => 125000,
            'monthly_contribution' => 25000,
        ]);

        $this->assertIsInt($goal->target_amount);
        $this->assertIsInt($goal->current_balance);
        $this->assertIsInt($goal->monthly_contribution);
    }
}
