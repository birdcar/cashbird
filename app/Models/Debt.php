<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'type',
        'lender',
        'current_balance',
        'original_balance',
        'apr',
        'minimum_payment',
        'due_day',
        'is_in_recovery',
        'recovery_terms',
        'status',
        'paid_off_at',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'integer',
            'original_balance' => 'integer',
            'type' => DebtType::class,
            'apr' => 'decimal:3',
            'minimum_payment' => 'integer',
            'due_day' => 'integer',
            'is_in_recovery' => 'boolean',
            'recovery_terms' => 'array',
            'status' => DebtStatus::class,
            'paid_off_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<DebtPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /** @param Builder<Debt> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', DebtStatus::Active);
    }

    /** @param Builder<Debt> $query */
    public function scopeProjectable(Builder $query): void
    {
        $query->where('status', DebtStatus::Active)
            ->where('current_balance', '>', 0)
            ->where('minimum_payment', '>', 0);
    }
}
