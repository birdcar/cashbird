<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Debt> */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Chase Sapphire', 'Student Loan', 'Auto Loan', 'Personal Loan']),
            'type' => fake()->randomElement(['credit_card', 'student_loan', 'personal_loan', 'auto_loan']),
            'lender' => fake()->company(),
            'current_balance' => fake()->numberBetween(50000, 2000000),
            'original_balance' => fake()->numberBetween(100000, 3000000),
            'apr' => fake()->randomFloat(3, 3.0, 29.99),
            'minimum_payment' => fake()->numberBetween(2500, 50000),
            'due_day' => fake()->numberBetween(1, 28),
            'status' => 'active',
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn () => [
            'type' => 'credit_card',
            'apr' => fake()->randomFloat(3, 15.0, 29.99),
        ]);
    }

    public function paydayLoan(): static
    {
        return $this->state(fn () => [
            'type' => 'payday_loan',
            'apr' => fake()->randomFloat(3, 100.0, 400.0),
            'current_balance' => fake()->numberBetween(10000, 100000),
        ]);
    }

    public function recoveryPlan(): static
    {
        return $this->state(fn () => [
            'type' => 'recovery_plan',
            'is_in_recovery' => true,
            'apr' => 0.0,
            'recovery_terms' => [
                'fixed_payment' => fake()->numberBetween(10000, 30000),
                'duration_months' => fake()->numberBetween(12, 36),
                'start_date' => now()->subMonths(fake()->numberBetween(1, 6))->toDateString(),
            ],
        ]);
    }

    public function paidOff(): static
    {
        return $this->state(fn () => [
            'status' => 'paid_off',
            'current_balance' => 0,
            'paid_off_at' => now(),
        ]);
    }

    public function linkedToAccount(string $accountId): static
    {
        return $this->state(fn () => [
            'account_id' => $accountId,
        ]);
    }
}
