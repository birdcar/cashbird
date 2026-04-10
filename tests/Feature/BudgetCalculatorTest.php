<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\BudgetCalculator;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private BudgetCalculator $calculator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->calculator = app(BudgetCalculator::class);
        $this->user = User::factory()->create();
    }

    public function test_estimates_monthly_income_from_deposits(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'amount' => 250000,
                'date' => Carbon::now()->subMonths($i)->startOfMonth()->addDays(15),
            ]);
        }

        $income = $this->calculator->estimateMonthlyIncome($this->user->id);

        $this->assertEquals(250000, $income);
    }

    public function test_generates_initial_budget_with_zero_based_constraint(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'amount' => 500000,
                'date' => Carbon::now()->subMonths($i)->startOfMonth()->addDays(15),
            ]);
        }

        $groceries = Category::where('name', 'Groceries')->first();
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'amount' => -30000,
                'category_id' => $groceries->id,
                'date' => Carbon::now()->subMonths($i)->startOfMonth()->addDays(5),
            ]);
        }

        $period = $this->calculator->generateInitialBudget($this->user->id);

        $this->assertNotNull($period);
        $this->assertEquals(500000, $period->total_income);
        $this->assertGreaterThan(0, $period->allocations()->count());
        $this->assertEquals(
            $period->total_income,
            $period->total_allocated,
            'Zero-based: total_allocated must equal total_income'
        );
    }

    public function test_recurring_charges_become_fixed_allocations(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'amount' => 500000,
                'date' => Carbon::now()->subMonths($i)->startOfMonth()->addDays(15),
            ]);
        }

        $streaming = Category::where('name', 'Streaming')->first();
        RecurringCharge::factory()->create([
            'user_id' => $this->user->id,
            'merchant_name' => 'NETFLIX',
            'category_id' => $streaming->id,
            'average_amount' => 1599,
            'frequency' => 'monthly',
        ]);

        $period = $this->calculator->generateInitialBudget($this->user->id);

        $netflixAllocation = $period->allocations()
            ->where('category_id', $streaming->id)
            ->first();

        $this->assertNotNull($netflixAllocation);
        $this->assertTrue($netflixAllocation->is_fixed);
        $this->assertEquals(1599, $netflixAllocation->allocated_amount);
    }

    public function test_rebalance_respects_locked_allocations(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $period = BudgetPeriod::factory()->create([
            'budget_id' => $budget->id,
            'total_income' => 500000,
        ]);

        $rent = Category::where('name', 'Rent/Mortgage')->first();
        $groceries = Category::where('name', 'Groceries')->first();
        $restaurants = Category::where('name', 'Restaurants')->first();

        $locked = BudgetAllocation::factory()->create([
            'budget_period_id' => $period->id,
            'category_id' => $rent->id,
            'allocated_amount' => 150000,
            'is_locked' => true,
        ]);

        BudgetAllocation::factory()->create([
            'budget_period_id' => $period->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 50000,
        ]);

        BudgetAllocation::factory()->create([
            'budget_period_id' => $period->id,
            'category_id' => $restaurants->id,
            'allocated_amount' => 30000,
        ]);

        $this->calculator->rebalance($period, [$locked->id]);

        $locked->refresh();
        $this->assertEquals(150000, $locked->allocated_amount);

        $totalAfter = $period->allocations()->sum('allocated_amount');
        $this->assertEquals(500000, $totalAfter);
    }

    public function test_zero_income_produces_empty_allocations(): void
    {
        $income = $this->calculator->estimateMonthlyIncome($this->user->id);

        $this->assertEquals(0, $income);
    }
}
