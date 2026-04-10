<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BudgetAllocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAllocation extends Model
{
    /** @use HasFactory<BudgetAllocationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'budget_period_id',
        'category_id',
        'allocated_amount',
        'spent_amount',
        'is_locked',
        'is_fixed',
        'lock_reason',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'integer',
            'spent_amount' => 'integer',
            'is_locked' => 'boolean',
            'is_fixed' => 'boolean',
        ];
    }

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
