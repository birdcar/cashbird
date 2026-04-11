<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\SavingsStage;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\Budget\SavingsStageAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsStageAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private SavingsStageAdvisor $advisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->advisor = app(SavingsStageAdvisor::class);
    }

    public function test_starter_emergency_fund_stage(): void
    {
        $user = User::factory()->create();
        Debt::factory()->create(['user_id' => $user->id, 'status' => DebtStatus::Active]);

        $this->assertEquals(SavingsStage::StarterEmergencyFund, $this->advisor->currentStage($user->id));
        $this->assertEquals(10, $this->advisor->recommendedSavingsPercent($user->id));
    }

    public function test_debt_payoff_stage(): void
    {
        $user = User::factory()->create();
        Debt::factory()->create(['user_id' => $user->id, 'status' => DebtStatus::Active]);
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 100000,
        ]);

        $this->assertEquals(SavingsStage::DebtPayoff, $this->advisor->currentStage($user->id));
        $this->assertEquals(10, $this->advisor->recommendedSavingsPercent($user->id));
    }

    public function test_full_emergency_fund_stage(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 50000,
        ]);

        $this->assertEquals(SavingsStage::FullEmergencyFund, $this->advisor->currentStage($user->id));
        $this->assertEquals(30, $this->advisor->recommendedSavingsPercent($user->id));
    }

    public function test_named_goals_stage(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 999999999,
        ]);

        $this->assertEquals(SavingsStage::NamedGoals, $this->advisor->currentStage($user->id));
        $this->assertEquals(30, $this->advisor->recommendedSavingsPercent($user->id));
    }

    public function test_ensure_system_goal_creates_starter_fund(): void
    {
        $user = User::factory()->create();
        Debt::factory()->create(['user_id' => $user->id, 'status' => DebtStatus::Active]);

        $goal = $this->advisor->ensureSystemGoal($user->id);

        $this->assertNotNull($goal);
        $this->assertEquals('Emergency Fund', $goal->name);
        $this->assertTrue($goal->is_system);
        $this->assertEquals(100000, $goal->target_amount);
    }

    public function test_ensure_system_goal_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        Debt::factory()->create(['user_id' => $user->id, 'status' => DebtStatus::Active]);

        $this->advisor->ensureSystemGoal($user->id);
        $this->advisor->ensureSystemGoal($user->id);

        $this->assertEquals(1, SavingsGoal::where('user_id', $user->id)->count());
    }

    public function test_ensure_system_goal_returns_null_for_named_goals_stage(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->emergencyFund()->create([
            'user_id' => $user->id,
            'current_balance' => 999999999,
        ]);

        $this->assertNull($this->advisor->ensureSystemGoal($user->id));
    }
}
