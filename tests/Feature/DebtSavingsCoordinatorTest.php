<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\GoalStatus;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\BudgetProposal;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\Budget\DebtSavingsCoordinator;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtSavingsCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private DebtSavingsCoordinator $coordinator;

    private User $user;

    private BudgetPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->coordinator = new DebtSavingsCoordinator;
        $this->user = User::factory()->create();

        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $this->period = BudgetPeriod::factory()->create([
            'budget_id' => $budget->id,
            'total_income' => 500000,
            'status' => 'active',
        ]);
    }

    public function test_creates_proposal_when_all_debts_paid_off(): void
    {
        Debt::factory()->paidOff()->create([
            'user_id' => $this->user->id,
            'minimum_payment' => 20000,
        ]);
        Debt::factory()->paidOff()->create([
            'user_id' => $this->user->id,
            'minimum_payment' => 15000,
        ]);

        SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Emergency Fund',
            'status' => GoalStatus::Active,
            'priority' => 0,
        ]);

        $proposal = $this->coordinator->checkAndPropose($this->user->id);

        $this->assertNotNull($proposal);
        $this->assertEquals('debt_coordinator', $proposal->proposed_by);
        $this->assertEquals('pending', $proposal->status);
        $this->assertStringContainsString('350.00', $proposal->changes[0]['rationale']);
        $this->assertStringContainsString('Emergency Fund', $proposal->changes[0]['rationale']);
    }

    public function test_returns_null_with_active_debts(): void
    {
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'status' => DebtStatus::Active,
        ]);

        SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'status' => GoalStatus::Active,
        ]);

        $result = $this->coordinator->checkAndPropose($this->user->id);

        $this->assertNull($result);
    }

    public function test_returns_null_without_savings_goal(): void
    {
        Debt::factory()->paidOff()->create([
            'user_id' => $this->user->id,
            'minimum_payment' => 20000,
        ]);

        $result = $this->coordinator->checkAndPropose($this->user->id);

        $this->assertNull($result);
    }

    public function test_prevents_duplicate_proposals(): void
    {
        Debt::factory()->paidOff()->create([
            'user_id' => $this->user->id,
            'minimum_payment' => 20000,
        ]);

        SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'status' => GoalStatus::Active,
            'priority' => 0,
        ]);

        $first = $this->coordinator->checkAndPropose($this->user->id);
        $this->assertNotNull($first);

        $second = $this->coordinator->checkAndPropose($this->user->id);
        $this->assertNull($second);

        $this->assertEquals(1, BudgetProposal::where('proposed_by', 'debt_coordinator')->count());
    }

    public function test_returns_null_with_no_paid_off_debts(): void
    {
        SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'status' => GoalStatus::Active,
        ]);

        $result = $this->coordinator->checkAndPropose($this->user->id);

        $this->assertNull($result);
    }

    public function test_selects_highest_priority_goal(): void
    {
        Debt::factory()->paidOff()->create([
            'user_id' => $this->user->id,
            'minimum_payment' => 20000,
        ]);

        SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Vacation',
            'status' => GoalStatus::Active,
            'priority' => 5,
        ]);
        SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Emergency Fund',
            'status' => GoalStatus::Active,
            'priority' => 0,
        ]);

        $proposal = $this->coordinator->checkAndPropose($this->user->id);

        $this->assertNotNull($proposal);
        $this->assertStringContainsString('Emergency Fund', $proposal->changes[0]['rationale']);
    }
}
