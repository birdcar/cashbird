<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetAllocation> */
class BudgetAllocationFactory extends Factory
{
    protected $model = BudgetAllocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'budget_period_id' => BudgetPeriod::factory(),
            'category_id' => Category::factory(),
            'allocated_amount' => fake()->numberBetween(5000, 50000),
            'spent_amount' => 0,
            'is_locked' => false,
            'is_fixed' => false,
        ];
    }
}
