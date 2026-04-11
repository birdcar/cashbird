<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InsightSeverity;
use App\Enums\InsightStatus;
use App\Enums\InsightType;
use App\Models\Insight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Insight> */
class InsightFactory extends Factory
{
    protected $model = Insight::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(InsightType::cases()),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'data' => ['amount' => fake()->numberBetween(1000, 50000)],
            'severity' => InsightSeverity::Info,
            'status' => InsightStatus::Active,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'status' => InsightStatus::Dismissed,
            'dismissed_at' => now(),
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn () => [
            'severity' => InsightSeverity::Warning,
        ]);
    }

    public function actionRequired(): static
    {
        return $this->state(fn () => [
            'severity' => InsightSeverity::ActionRequired,
        ]);
    }
}
