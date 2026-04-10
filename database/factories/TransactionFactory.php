<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'teller_id' => 'txn_' . fake()->unique()->bothify('####????'),
            'amount' => fake()->numberBetween(-50000, 50000),
            'date' => fake()->dateTimeBetween('-90 days'),
            'description' => fake()->sentence(3),
            'merchant_name' => fake()->company(),
            'status' => 'posted',
            'type' => fake()->randomElement(['card_payment', 'transfer', 'deposit', 'withdrawal']),
        ];
    }
}
