<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentSource;
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
        $amount = fake()->numberBetween(5000, 50000);
        $interest = fake()->numberBetween(1000, (int) round($amount * 0.3));
        $principal = $amount - $interest;

        return [
            'debt_id' => Debt::factory(),
            'amount' => $amount,
            'principal' => $principal,
            'interest' => $interest,
            'balance_after' => fake()->numberBetween(50000, 2000000),
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'source' => PaymentSource::Detected,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'source' => PaymentSource::Manual,
        ]);
    }
}
