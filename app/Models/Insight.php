<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InsightSeverity;
use App\Enums\InsightStatus;
use App\Enums\InsightType;
use Database\Factories\InsightFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
    /** @use HasFactory<InsightFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'data',
        'severity',
        'status',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InsightType::class,
            'severity' => InsightSeverity::class,
            'status' => InsightStatus::class,
            'data' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Insight> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', InsightStatus::Active);
    }

    public function dismiss(): void
    {
        $this->update([
            'status' => InsightStatus::Dismissed,
            'dismissed_at' => now(),
        ]);
    }
}
