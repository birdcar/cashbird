<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use App\Services\Debt\PayoffProjector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoffProjectorTest extends TestCase
{
    use RefreshDatabase;

    private PayoffProjector $projector;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projector = app(PayoffProjector::class);
        $this->user = User::factory()->create();
    }

    public function test_at_current_rate_uses_zero_extra(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 20.0,
            'current_balance' => 1000000,
            'minimum_payment' => 20000,
        ]);

        $baseline = $this->projector->atCurrentRate(collect([$debt]));

        $this->assertGreaterThan(0, $baseline->monthsToDebtFree);
        $this->assertGreaterThan(0, $baseline->totalInterestPaid);
    }

    public function test_with_extra_reduces_months_and_interest(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 20.0,
            'current_balance' => 1000000,
            'minimum_payment' => 20000,
        ]);

        $baseline = $this->projector->atCurrentRate(collect([$debt]));
        $withExtra = $this->projector->withExtra(collect([$debt]), 20000);

        $this->assertLessThan($baseline->monthsToDebtFree, $withExtra->monthsToDebtFree);
        $this->assertLessThan($baseline->totalInterestPaid, $withExtra->totalInterestPaid);
    }

    public function test_compare_scenarios_returns_ordered_results(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 20.0,
            'current_balance' => 1000000,
            'minimum_payment' => 20000,
        ]);

        $scenarios = $this->projector->compareScenarios(
            collect([$debt]),
            [0, 10000, 20000, 50000]
        );

        $this->assertCount(4, $scenarios);

        // More extra = fewer months
        $this->assertGreaterThan($scenarios[1]['months'], $scenarios[0]['months']);
        $this->assertGreaterThan($scenarios[2]['months'], $scenarios[1]['months']);
        $this->assertGreaterThan($scenarios[3]['months'], $scenarios[2]['months']);

        // More extra = less interest
        $this->assertGreaterThan($scenarios[1]['total_interest'], $scenarios[0]['total_interest']);
    }

    public function test_debt_free_date_returns_carbon_instance(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 18.0,
            'current_balance' => 500000,
            'minimum_payment' => 15000,
        ]);

        $date = $this->projector->debtFreeDate(collect([$debt]), 10000);

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertTrue($date->isAfter(now()));
    }

    public function test_total_interest_saved_is_positive_with_extra(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 20.0,
            'current_balance' => 1000000,
            'minimum_payment' => 20000,
        ]);

        $saved = $this->projector->totalInterestSaved(collect([$debt]), 20000);

        $this->assertGreaterThan(0, $saved);
    }

    public function test_total_interest_saved_is_zero_with_no_extra(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 20.0,
            'current_balance' => 1000000,
            'minimum_payment' => 20000,
        ]);

        $saved = $this->projector->totalInterestSaved(collect([$debt]), 0);

        $this->assertEquals(0, $saved);
    }

    public function test_empty_debts_returns_zero_months(): void
    {
        $schedule = $this->projector->atCurrentRate(collect());

        $this->assertEquals(0, $schedule->monthsToDebtFree);
        $this->assertEquals(0, $schedule->totalInterestPaid);
    }
}
