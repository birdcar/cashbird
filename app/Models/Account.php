<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'teller_id',
        'institution_id',
        'name',
        'type',
        'subtype',
        'currency',
        'balance_current',
        'balance_available',
        'balance_limit',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'balance_current' => 'integer',
            'balance_available' => 'integer',
            'balance_limit' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TellerEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TellerEnrollment::class, 'enrollment_id');
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
