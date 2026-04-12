<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Connection;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Connection> */
class ConnectionFactory extends Factory
{
    protected $model = Connection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'institution_id' => Institution::factory(),
            'stripe_account_id' => 'fca_'.fake()->uuid(),
            'status' => 'active',
            'connected_at' => now(),
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn () => ['status' => 'disconnected']);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => 'expired']);
    }
}
