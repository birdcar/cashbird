<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SharingRelation;
use App\Enums\SharingStatus;
use App\Models\SharingInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SharingInvitation> */
class SharingInvitationFactory extends Factory
{
    protected $model = SharingInvitation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'resource_type' => 'budget_category',
            'resource_id' => Str::uuid()->toString(),
            'relation' => SharingRelation::Viewer,
            'status' => SharingStatus::Active,
        ];
    }

    public function editor(): static
    {
        return $this->state(fn () => [
            'relation' => SharingRelation::Editor,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => SharingStatus::Revoked,
        ]);
    }
}
