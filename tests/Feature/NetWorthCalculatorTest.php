<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Debt;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\Services\NetWorthCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetWorthCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private NetWorthCalculator $calculator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new NetWorthCalculator;
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_computes_net_worth_with_accounts_and_manual_debts(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'balance_current' => 500000,
        ]);
        Account::factory()->create([
            'user_id' => $this->user->id,
            'balance_current' => 300000,
        ]);
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => null,
            'current_balance' => 200000,
        ]);

        $result = $this->calculator->compute($this->user->id);

        $this->assertEquals(800000, $result['total_assets']);
        $this->assertEquals(200000, $result['total_debts']);
        $this->assertEquals(600000, $result['net_worth']);
    }

    public function test_does_not_double_count_debts_linked_to_accounts(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance_current' => 500000,
        ]);

        $creditCardAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance_current' => -100000,
        ]);

        // This debt is synced from the credit card account — should be excluded
        Debt::factory()->linkedToAccount($creditCardAccount->id)->create([
            'user_id' => $this->user->id,
            'current_balance' => 100000,
        ]);

        $result = $this->calculator->compute($this->user->id);

        // Assets: 500000 + (-100000) = 400000
        // Debts: 0 (linked debt excluded)
        // Net worth: 400000
        $this->assertEquals(400000, $result['total_assets']);
        $this->assertEquals(0, $result['total_debts']);
        $this->assertEquals(400000, $result['net_worth']);
    }

    public function test_computes_zero_net_worth_with_no_accounts(): void
    {
        $result = $this->calculator->compute($this->user->id);

        $this->assertEquals(0, $result['total_assets']);
        $this->assertEquals(0, $result['total_debts']);
        $this->assertEquals(0, $result['net_worth']);
        $this->assertEmpty($result['breakdown']['accounts']);
        $this->assertEmpty($result['breakdown']['debts']);
    }

    public function test_breakdown_includes_account_and_debt_details(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Checking',
            'type' => 'checking',
            'balance_current' => 300000,
        ]);
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => null,
            'name' => 'Personal Loan',
            'current_balance' => 100000,
        ]);

        $result = $this->calculator->compute($this->user->id);

        $this->assertCount(1, $result['breakdown']['accounts']);
        $this->assertEquals('Checking', $result['breakdown']['accounts'][0]['name']);
        $this->assertEquals(300000, $result['breakdown']['accounts'][0]['balance']);

        $this->assertCount(1, $result['breakdown']['debts']);
        $this->assertEquals('Personal Loan', $result['breakdown']['debts'][0]['name']);
        $this->assertEquals(100000, $result['breakdown']['debts'][0]['balance']);
    }

    public function test_month_over_month_change_with_two_snapshots(): void
    {
        NetWorthSnapshot::factory()->create([
            'user_id' => $this->user->id,
            'month' => '2026-03-01',
            'net_worth' => 500000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $this->user->id,
            'month' => '2026-04-01',
            'net_worth' => 600000,
        ]);

        $change = $this->calculator->monthOverMonthChange($this->user->id);

        $this->assertEquals(100000, $change);
    }

    public function test_month_over_month_change_returns_null_with_one_snapshot(): void
    {
        NetWorthSnapshot::factory()->create([
            'user_id' => $this->user->id,
            'month' => '2026-04-01',
            'net_worth' => 600000,
        ]);

        $change = $this->calculator->monthOverMonthChange($this->user->id);

        $this->assertNull($change);
    }

    public function test_trend_returns_most_recent_months_in_ascending_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        // Create 15 months of history (more than the 6 we'll request)
        for ($i = 14; $i >= 0; $i--) {
            NetWorthSnapshot::factory()->create([
                'user_id' => $this->user->id,
                'month' => Carbon::parse('2026-04-01')->subMonths($i)->startOfMonth(),
                'net_worth' => 100000 + ($i * 10000),
            ]);
        }

        $trend = $this->calculator->trend($this->user->id, 6);

        $this->assertCount(6, $trend);

        $months = $trend->pluck('month')->map(fn ($m) => $m->format('Y-m'))->toArray();

        // Verify ascending order
        $sorted = $months;
        sort($sorted);
        $this->assertEquals($sorted, $months);

        // Verify these are the 6 MOST RECENT months, not the oldest
        $this->assertEquals('2025-11', $months[0]);
        $this->assertEquals('2026-04', $months[5]);
    }

    public function test_ignores_other_users_data(): void
    {
        $otherUser = User::factory()->create();
        Account::factory()->create([
            'user_id' => $otherUser->id,
            'balance_current' => 999999,
        ]);

        Account::factory()->create([
            'user_id' => $this->user->id,
            'balance_current' => 100000,
        ]);

        $result = $this->calculator->compute($this->user->id);

        $this->assertEquals(100000, $result['total_assets']);
    }
}
