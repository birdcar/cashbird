<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SharingRelation;
use App\Enums\SharingStatus;
use Database\Factories\SharingInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharingInvitation extends Model
{
    /** @use HasFactory<SharingInvitationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'resource_type',
        'resource_id',
        'relation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'relation' => SharingRelation::class,
            'status' => SharingStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /** @param Builder<SharingInvitation> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', SharingStatus::Active);
    }

    public function revoke(): void
    {
        $this->update(['status' => SharingStatus::Revoked]);
    }
}
