<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecurringCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecurringCharge> */
class RecurringChargeFactory extends Factory
{
    protected $model = RecurringCharge::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'merchant_name' => fake()->company(),
            'average_amount' => fake()->numberBetween(500, 20000),
            'frequency' => 'monthly',
            'confidence' => fake()->randomFloat(2, 0.7, 1.0),
            'last_seen_at' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
