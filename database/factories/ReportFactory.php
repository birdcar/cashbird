<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period_month' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-01'),
            'title' => 'Monthly Report — '.fake()->monthName().' '.fake()->year(),
            'content' => '## Monthly Summary\n\nTest report content.',
            'summary' => 'This is a test report summary.',
            'data' => [
                'total_income' => fake()->numberBetween(300000, 800000),
                'total_spending' => fake()->numberBetween(200000, 700000),
                'categories' => [],
            ],
        ];
    }
}
