<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BudgetProposalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetProposal extends Model
{
    /** @use HasFactory<BudgetProposalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'budget_period_id',
        'proposed_by',
        'changes',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }
}
