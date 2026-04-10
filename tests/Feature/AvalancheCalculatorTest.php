<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use App\Services\Debt\AvalancheCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvalancheCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private AvalancheCalculator $calculator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(AvalancheCalculator::class);
        $this->user = User::factory()->create();
    }

    public function test_sorts_debts_by_apr_descending(): void
    {
        $low = Debt::factory()->create(['user_id' => $this->user->id, 'apr' => 5.0]);
        $high = Debt::factory()->create(['user_id' => $this->user->id, 'apr' => 24.99]);
        $mid = Debt::factory()->create(['user_id' => $this->user->id, 'apr' => 15.0]);

        $ordered = $this->calculator->calculatePayoffOrder(collect([$low, $high, $mid]));

        $this->assertEquals($high->id, $ordered[0]->id);
        $this->assertEquals($mid->id, $ordered[1]->id);
        $this->assertEquals($low->id, $ordered[2]->id);
    }

    public function test_payday_loan_sorts_before_credit_card(): void
    {
        $cc = Debt::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'apr' => 24.99,
            'current_balance' => 500000,
            'minimum_payment' => 10000,
        ]);
        $payday = Debt::factory()->paydayLoan()->create([
            'user_id' => $this->user->id,
            'apr' => 200.0,
            'current_balance' => 50000,
            'minimum_payment' => 5000,
        ]);

        $ordered = $this->calculator->calculatePayoffOrder(collect([$cc, $payday]));

        $this->assertEquals($payday->id, $ordered[0]->id);
        $this->assertEquals($cc->id, $ordered[1]->id);
    }

    public function test_allocates_extra_to_highest_apr_first(): void
    {
        $high = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 24.99,
            'current_balance' => 500000,
        ]);
        $low = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 5.0,
            'current_balance' => 1000000,
        ]);

        $result = $this->calculator->allocateExtraPayment(collect([$low, $high]), 30000);

        $this->assertArrayHasKey($high->id, $result['allocations']);
        $this->assertEquals(30000, $result['allocations'][$high->id]);
        $this->assertArrayNotHasKey($low->id, $result['allocations']);
        $this->assertEquals(0, $result['remainder']);
    }

    public function test_extra_overflows_to_next_debt_when_balance_covered(): void
    {
        $high = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 24.99,
            'current_balance' => 10000,
        ]);
        $low = Debt::factory()->create([
            'user_id' => $this->user->id,
            'apr' => 5.0,
            'current_balance' => 1000000,
        ]);

        $result = $this->calculator->allocateExtraPayment(collect([$low, $high]), 30000);

        $this->assertEquals(10000, $result['allocations'][$high->id]);
        $this->assertEquals(20000, $result['allocations'][$low->id]);
        $this->assertEquals(0, $result['remainder']);
    }

    public function test_skips_recovery_plans_for_extra_allocation(): void
    {
        $recovery = Debt::factory()->recoveryPlan()->create([
            'user_id' => $this->user->id,
            'current_balance' => 300000,
            'minimum_payment' => 15000,
        ]);
        $cc = Debt::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'apr' => 20.0,
            'current_balance' => 500000,
        ]);

        $result = $this->calculator->allocateExtraPayment(collect([$recovery, $cc]), 30000);

        $this->assertArrayNotHasKey($recovery->id, $result['allocations']);
        $this->assertEquals(30000, $result['allocations'][$cc->id]);
    }

    public function test_snowball_rollup_freed_minimum_adds_to_extra(): void
    {
        // Payday: $1000 at 200% APR, $200/mo min → high APR, small balance
        $payday = Debt::factory()->paydayLoan()->create([
            'user_id' => $this->user->id,
            'name' => 'Payday Loan',
            'apr' => 200.0,
            'current_balance' => 100000,
            'minimum_payment' => 20000,
        ]);
        // CC: $5000 at 18% APR, $200/mo min → lower APR, larger balance
        $cc = Debt::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'name' => 'Credit Card',
            'apr' => 18.0,
            'current_balance' => 500000,
            'minimum_payment' => 20000,
        ]);

        // $500/mo extra: avalanche targets payday first (highest APR)
        $schedule = $this->calculator->projectPayoffSchedule(collect([$payday, $cc]), 50000);

        $this->assertGreaterThan(0, $schedule->milestones->count());
        $this->assertEquals(2, $schedule->milestones->count());

        $paydayMilestone = $schedule->milestones->firstWhere('debt_name', 'Payday Loan');
        $this->assertNotNull($paydayMilestone);

        $ccMilestone = $schedule->milestones->firstWhere('debt_name', 'Credit Card');
        $this->assertNotNull($ccMilestone);

        // Payday should pay off before CC
        $this->assertLessThan($ccMilestone['payoff_month'], $paydayMilestone['payoff_month']);
    }

    public function test_single_debt_payoff_projection(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Card',
            'apr' => 18.0,
            'current_balance' => 1000000,
            'minimum_payment' => 20000,
        ]);

        $schedule = $this->calculator->projectPayoffSchedule(collect([$debt]), 0);

        $this->assertGreaterThan(0, $schedule->monthsToDebtFree);
        $this->assertGreaterThan(0, $schedule->totalInterestPaid);
        $this->assertEquals(1, $schedule->milestones->count());
        $this->assertEquals('Test Card', $schedule->milestones[0]['debt_name']);
    }

    public function test_recovery_plan_uses_fixed_payment(): void
    {
        $recovery = Debt::factory()->recoveryPlan()->create([
            'user_id' => $this->user->id,
            'name' => 'Recovery Plan',
            'current_balance' => 300000,
            'minimum_payment' => 15000,
            'recovery_terms' => [
                'fixed_payment' => 15000,
                'duration_months' => 20,
                'start_date' => now()->subMonths(2)->toDateString(),
            ],
        ]);

        $schedule = $this->calculator->projectPayoffSchedule(collect([$recovery]), 10000);

        $this->assertEquals(1, $schedule->milestones->count());
        // Recovery at 0% APR with 15000/mo fixed on 300000 balance = 20 months
        $this->assertEquals(20, $schedule->monthsToDebtFree);
    }

    public function test_empty_debts_returns_zero_schedule(): void
    {
        $schedule = $this->calculator->projectPayoffSchedule(collect(), 10000);

        $this->assertEquals(0, $schedule->monthsToDebtFree);
        $this->assertEquals(0, $schedule->totalInterestPaid);
        $this->assertEquals(0, $schedule->milestones->count());
    }

    public function test_paid_off_debts_are_excluded(): void
    {
        Debt::factory()->paidOff()->create([
            'user_id' => $this->user->id,
            'name' => 'Paid Off Card',
        ]);

        $active = Debt::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Active Card',
            'apr' => 20.0,
            'current_balance' => 100000,
            'minimum_payment' => 10000,
        ]);

        $debts = $this->user->debts;
        $schedule = $this->calculator->projectPayoffSchedule($debts, 0);

        $this->assertEquals(1, $schedule->milestones->count());
        $this->assertEquals('Active Card', $schedule->milestones[0]['debt_name']);
    }

    public function test_negative_amortization_guard_prevents_infinite_loop(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Underwater Debt',
            'apr' => 300.0,
            'current_balance' => 1000000,
            'minimum_payment' => 1000,
        ]);

        $schedule = $this->calculator->projectPayoffSchedule(collect([$debt]), 0);

        // Should terminate at MAX_MONTHS (600) rather than looping forever
        $this->assertEquals(600, $schedule->monthsToDebtFree);
    }
}
