<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DebtPayment> */
class DebtPaymentFactory extends Factory
{
    protected $model = DebtPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'debt_id' => Debt::factory(),
            'amount' => fake()->numberBetween(5000, 50000),
            'principal' => fake()->numberBetween(3000, 40000),
            'interest' => fake()->numberBetween(1000, 10000),
            'balance_after' => fake()->numberBetween(50000, 2000000),
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'source' => 'detected',
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'source' => 'manual',
        ]);
    }
}
