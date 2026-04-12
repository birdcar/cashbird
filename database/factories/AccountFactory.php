<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Connection;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'connection_id' => Connection::factory(),
            'external_id' => 'acc_'.fake()->unique()->bothify('####????'),
            'institution_id' => Institution::factory(),
            'name' => fake()->randomElement(['Checking', 'Savings', 'Credit Card', 'Mortgage']),
            'type' => fake()->randomElement(['checking', 'savings', 'credit_card', 'loan', 'investment']),
            'subtype' => null,
            'currency' => 'USD',
            'balance_current' => fake()->numberBetween(10000, 500000),
            'balance_available' => fake()->numberBetween(10000, 500000),
            'balance_limit' => null,
            'last_synced_at' => now(),
        ];
    }
}
