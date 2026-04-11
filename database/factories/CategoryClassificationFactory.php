<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BudgetCategory;
use App\Models\Category;
use App\Models\CategoryClassification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CategoryClassification> */
class CategoryClassificationFactory extends Factory
{
    protected $model = CategoryClassification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'classification' => fake()->randomElement(BudgetCategory::cases()),
            'is_ai_assigned' => true,
        ];
    }

    public function need(): static
    {
        return $this->state(fn () => [
            'classification' => BudgetCategory::Need,
        ]);
    }

    public function want(): static
    {
        return $this->state(fn () => [
            'classification' => BudgetCategory::Want,
        ]);
    }

    public function savings(): static
    {
        return $this->state(fn () => [
            'classification' => BudgetCategory::Savings,
        ]);
    }

    public function userOverride(): static
    {
        return $this->state(fn () => [
            'is_ai_assigned' => false,
        ]);
    }
}
