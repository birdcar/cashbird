<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoalStatus;
use Database\Factories\SavingsGoalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsGoal extends Model
{
    /** @use HasFactory<SavingsGoalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'current_balance',
        'target_date',
        'monthly_contribution',
        'priority',
        'status',
        'is_system',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'current_balance' => 'integer',
            'target_date' => 'date',
            'monthly_contribution' => 'integer',
            'priority' => 'integer',
            'status' => GoalStatus::class,
            'is_system' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => GoalStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
