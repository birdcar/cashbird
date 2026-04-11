<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BudgetProposal;
use App\Models\User;
use App\Services\Budget\DebtSavingsCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBudgetProposal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        $period = $this->user->currentBudgetPeriod();

        if (! $period) {
            return;
        }

        $changes = [];
        $allocations = $period->allocations()->with('category')->get();

        foreach ($allocations as $allocation) {
            if ($allocation->is_locked || $allocation->is_fixed) {
                continue;
            }

            $diff = $allocation->allocated_amount - $allocation->spent_amount;
            $percentUsed = $allocation->allocated_amount > 0
                ? ($allocation->spent_amount / $allocation->allocated_amount) * 100
                : 0;

            if ($percentUsed > 110) {
                $increase = (int) round($allocation->spent_amount * 1.05);
                $changes[] = [
                    'category_id' => $allocation->category_id,
                    'category_name' => $allocation->category?->name,
                    'old_amount' => $allocation->allocated_amount,
                    'new_amount' => $increase,
                    'rationale' => sprintf(
                        'Overspent by %s — increasing to match actual spending + 5%% buffer',
                        '$'.number_format(abs($diff) / 100, 2),
                    ),
                ];
            } elseif ($percentUsed < 50 && $allocation->allocated_amount > 5000) {
                $decrease = max(5000, (int) round($allocation->spent_amount * 1.2));
                $changes[] = [
                    'category_id' => $allocation->category_id,
                    'category_name' => $allocation->category?->name,
                    'old_amount' => $allocation->allocated_amount,
                    'new_amount' => $decrease,
                    'rationale' => sprintf(
                        'Only used %d%% of allocation — reducing to free up funds',
                        (int) $percentUsed,
                    ),
                ];
            }
        }

        if (! empty($changes)) {
            BudgetProposal::create([
                'budget_period_id' => $period->id,
                'proposed_by' => 'ai',
                'changes' => $changes,
                'status' => 'pending',
            ]);
        }

        app(DebtSavingsCoordinator::class)->checkAndPropose($this->user->id);
    }
}
