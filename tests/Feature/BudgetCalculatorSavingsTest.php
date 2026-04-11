<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Budget\BudgetCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCalculatorSavingsTest extends TestCase
{
    use RefreshDatabase;

    private BudgetCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(BudgetCalculator::class);
    }

    public function test_calculate_split_default_50_30_20(): void
    {
        // Default: 50% needs, 30% savings, 20% wants
        $split = $this->calculator->calculateSplit(500000, 30);

        $this->assertEquals(150000, $split['savingsAmount']);
        $this->assertEquals(250000, $split['needsTarget']);
        $this->assertEquals(100000, $split['wantsTarget']);
        $this->assertEquals(500000, $split['savingsAmount'] + $split['needsTarget'] + $split['wantsTarget']);
    }

    public function test_calculate_split_with_custom_needs_percent(): void
    {
        // 60% needs, 30% savings, 10% wants
        $split = $this->calculator->calculateSplit(500000, 30, 60);

        $this->assertEquals(150000, $split['savingsAmount']);
        $this->assertEquals(300000, $split['needsTarget']);
        $this->assertEquals(50000, $split['wantsTarget']);
    }

    public function test_calculate_split_with_zero_income(): void
    {
        $split = $this->calculator->calculateSplit(0, 30);

        $this->assertEquals(0, $split['savingsAmount']);
        $this->assertEquals(0, $split['needsTarget']);
        $this->assertEquals(0, $split['wantsTarget']);
    }

    public function test_calculate_split_with_ten_percent_savings(): void
    {
        // During debt payoff: 10% savings, 50% needs, 40% wants
        $split = $this->calculator->calculateSplit(500000, 10);

        $this->assertEquals(50000, $split['savingsAmount']);
        $this->assertEquals(250000, $split['needsTarget']);
        $this->assertEquals(200000, $split['wantsTarget']);
    }

    public function test_calculate_split_totals_equal_income(): void
    {
        $incomes = [100000, 250000, 500000, 750000, 1000000, 333333];

        foreach ($incomes as $income) {
            $split = $this->calculator->calculateSplit($income, 30);
            $total = $split['savingsAmount'] + $split['needsTarget'] + $split['wantsTarget'];
            $this->assertEquals($income, $total, "Split should sum to income ({$income}) but got {$total}");
        }
    }

    public function test_calculate_split_with_negative_income(): void
    {
        $split = $this->calculator->calculateSplit(-100000, 30);

        $this->assertEquals(0, $split['savingsAmount']);
        $this->assertEquals(0, $split['needsTarget']);
        $this->assertEquals(0, $split['wantsTarget']);
    }
}
