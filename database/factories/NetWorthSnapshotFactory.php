<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NetWorthSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NetWorthSnapshot> */
class NetWorthSnapshotFactory extends Factory
{
    protected $model = NetWorthSnapshot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $assets = fake()->numberBetween(500000, 5000000);
        $debts = fake()->numberBetween(100000, 3000000);

        return [
            'user_id' => User::factory(),
            'month' => fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-01'),
            'total_assets' => $assets,
            'total_debts' => $debts,
            'net_worth' => $assets - $debts,
            'breakdown' => [
                'accounts' => [
                    ['name' => 'Checking', 'type' => 'depository', 'balance' => $assets],
                ],
                'debts' => [
                    ['name' => 'Credit Card', 'type' => 'credit_card', 'balance' => $debts],
                ],
            ],
        ];
    }
}
