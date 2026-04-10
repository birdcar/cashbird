<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TellerEnrollmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TellerEnrollment extends Model
{
    /** @use HasFactory<TellerEnrollmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'institution_id',
        'access_token',
        'status',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'enrolled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'enrollment_id');
    }
}
