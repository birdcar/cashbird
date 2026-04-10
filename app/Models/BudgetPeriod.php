<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'budget_id',
        'month',
        'total_income',
        'total_allocated',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'total_income' => 'integer',
            'total_allocated' => 'integer',
        ];
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<BudgetAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    /** @return HasMany<BudgetProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(BudgetProposal::class);
    }
}
