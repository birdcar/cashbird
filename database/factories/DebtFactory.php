<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
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
        $type = fake()->randomElement(DebtType::cases());

        return [
            'user_id' => User::factory(),
            'name' => match ($type) {
                DebtType::CreditCard => fake()->randomElement(['Chase Sapphire', 'Citi Double Cash', 'Amex Gold']),
                DebtType::StudentLoan => fake()->randomElement(['Federal Student Loan', 'Sallie Mae Loan']),
                DebtType::AutoLoan => fake()->randomElement(['Toyota Financial', 'Honda Finance']),
                DebtType::PaydayLoan => fake()->randomElement(['MoneyLion Advance', 'Cleo Cash']),
                DebtType::Mortgage => fake()->randomElement(['Wells Fargo Mortgage', 'Chase Home Loan']),
                DebtType::RecoveryPlan => 'Recovery Plan',
                default => fake()->company().' Personal Loan',
            },
            'type' => $type,
            'lender' => fake()->company(),
            'current_balance' => fake()->numberBetween(50000, 2000000),
            'original_balance' => fake()->numberBetween(100000, 3000000),
            'apr' => fake()->randomFloat(3, 3.0, 29.99),
            'minimum_payment' => fake()->numberBetween(2500, 50000),
            'due_day' => fake()->numberBetween(1, 28),
            'status' => DebtStatus::Active,
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn () => [
            'type' => DebtType::CreditCard,
            'apr' => fake()->randomFloat(3, 15.0, 29.99),
        ]);
    }

    public function paydayLoan(): static
    {
        return $this->state(fn () => [
            'type' => DebtType::PaydayLoan,
            'apr' => fake()->randomFloat(3, 100.0, 400.0),
            'current_balance' => fake()->numberBetween(10000, 100000),
        ]);
    }

    public function studentLoan(): static
    {
        return $this->state(fn () => [
            'type' => DebtType::StudentLoan,
            'apr' => fake()->randomFloat(3, 3.5, 8.0),
        ]);
    }

    public function autoLoan(): static
    {
        return $this->state(fn () => [
            'type' => DebtType::AutoLoan,
            'apr' => fake()->randomFloat(3, 3.99, 19.99),
        ]);
    }

    public function mortgage(): static
    {
        return $this->state(fn () => [
            'type' => DebtType::Mortgage,
            'apr' => fake()->randomFloat(3, 2.75, 8.0),
            'current_balance' => fake()->numberBetween(5000000, 50000000),
            'original_balance' => fake()->numberBetween(10000000, 60000000),
            'minimum_payment' => fake()->numberBetween(100000, 400000),
        ]);
    }

    public function recoveryPlan(): static
    {
        return $this->state(fn () => [
            'type' => DebtType::RecoveryPlan,
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
            'status' => DebtStatus::PaidOff,
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
