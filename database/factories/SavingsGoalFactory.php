<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GoalStatus;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavingsGoal> */
class SavingsGoalFactory extends Factory
{
    protected $model = SavingsGoal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $target = fake()->numberBetween(100000, 1000000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Emergency Fund', 'Vacation', 'New Car', 'Home Down Payment', 'Holiday Gifts']),
            'target_amount' => $target,
            'current_balance' => fake()->numberBetween(0, $target),
            'target_date' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+2 years'),
            'monthly_contribution' => fake()->numberBetween(5000, 50000),
            'priority' => fake()->numberBetween(0, 5),
            'status' => GoalStatus::Active,
        ];
    }

    public function emergencyFund(): static
    {
        return $this->state(fn () => [
            'name' => 'Emergency Fund',
            'target_amount' => 100000,
            'is_system' => true,
            'priority' => 0,
        ]);
    }

    public function fullEmergencyFund(int $monthlyExpenses): static
    {
        return $this->state(fn () => [
            'name' => 'Emergency Fund',
            'target_amount' => $monthlyExpenses * 3,
            'is_system' => true,
            'priority' => 0,
        ]);
    }

    public function completed(): static
    {
        return $this->afterCreating(function (SavingsGoal $goal) {
            $goal->update([
                'status' => GoalStatus::Completed,
                'current_balance' => $goal->target_amount,
                'completed_at' => now(),
            ]);
        });
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => GoalStatus::Paused,
        ]);
    }
}
