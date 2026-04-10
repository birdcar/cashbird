<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Institution;
use App\Models\TellerEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TellerEnrollment> */
class TellerEnrollmentFactory extends Factory
{
    protected $model = TellerEnrollment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'institution_id' => Institution::factory(),
            'access_token' => 'test_token_' . fake()->uuid(),
            'status' => 'active',
            'enrolled_at' => now(),
        ];
    }
}
