<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'workos_id',
        'name',
        'slug',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public static function findByWorkOSId(string $workosId): ?static
    {
        return static::query()->where('workos_id', $workosId)->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function findOrCreateByWorkOS(array $data): static
    {
        return static::query()->firstOrCreate(
            ['workos_id' => $data['id']],
            [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
            ]
        );
    }
}