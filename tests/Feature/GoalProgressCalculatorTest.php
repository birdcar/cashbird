<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\Budget\GoalProgressCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalProgressCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private GoalProgressCalculator $calculator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GoalProgressCalculator;
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_computes_50_percent_progress(): void
    {
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 50000,
            'monthly_contribution' => 10000,
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertEquals(50, $result['progress']);
        $this->assertEquals(50000, $result['remaining']);
        $this->assertEquals(75, $result['next_milestone']);
    }

    public function test_progress_capped_at_100(): void
    {
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 150000,
            'monthly_contribution' => 10000,
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertEquals(100, $result['progress']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertNull($result['next_milestone']);
    }

    public function test_zero_target_returns_zero_progress(): void
    {
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 0,
            'current_balance' => 0,
            'monthly_contribution' => 0,
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertEquals(0, $result['progress']);
    }

    public function test_projected_completion_with_monthly_contribution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 40000,
            'monthly_contribution' => 10000,
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertNotNull($result['projected_completion']);
        // 60000 remaining / 10000 per month = 6 months
        $this->assertEquals('2026-10', $result['projected_completion']->format('Y-m'));
    }

    public function test_projected_completion_null_with_zero_contribution(): void
    {
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 40000,
            'monthly_contribution' => 0,
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertNull($result['projected_completion']);
    }

    public function test_on_track_null_with_no_target_date(): void
    {
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_date' => null,
            'monthly_contribution' => 10000,
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertNull($result['on_track']);
    }

    public function test_on_track_true_when_ahead_of_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 70000,
            'target_date' => '2026-12-01',
            'monthly_contribution' => 10000,
            'created_at' => Carbon::parse('2026-01-01'),
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertEquals('on_track', $result['on_track']);
    }

    public function test_at_risk_when_slightly_behind_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        // 5 months elapsed of 11 total = ~45% expected. 40% actual = 5pts behind (within 10pt threshold)
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 40000,
            'target_date' => '2026-12-01',
            'monthly_contribution' => 5000,
            'created_at' => Carbon::parse('2026-01-01'),
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertEquals('at_risk', $result['on_track']);
    }

    public function test_behind_when_far_behind_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        // 5 months elapsed of 11 total = ~45% expected. 10% actual = 35pts behind (> 10pt threshold)
        $goal = SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => 10000,
            'target_date' => '2026-12-01',
            'monthly_contribution' => 5000,
            'created_at' => Carbon::parse('2026-01-01'),
        ]);

        $result = $this->calculator->compute($goal);

        $this->assertEquals('behind', $result['on_track']);
    }

    public function test_milestone_markers_at_quartiles(): void
    {
        $makeGoal = fn (int $balance) => SavingsGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100000,
            'current_balance' => $balance,
            'monthly_contribution' => 10000,
        ]);

        $this->assertEquals(25, $this->calculator->compute($makeGoal(10000))['next_milestone']);
        $this->assertEquals(50, $this->calculator->compute($makeGoal(30000))['next_milestone']);
        $this->assertEquals(75, $this->calculator->compute($makeGoal(60000))['next_milestone']);
        $this->assertEquals(100, $this->calculator->compute($makeGoal(80000))['next_milestone']);
    }
}
