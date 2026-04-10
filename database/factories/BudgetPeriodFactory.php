<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetPeriod> */
class BudgetPeriodFactory extends Factory
{
    protected $model = BudgetPeriod::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'month' => Carbon::now()->startOfMonth()->toDateString(),
            'total_income' => fake()->numberBetween(300000, 1000000),
            'total_allocated' => 0,
            'status' => 'active',
        ];
    }
}
