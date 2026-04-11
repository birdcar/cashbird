<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetBudgetStatus implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Get current budget period status with allocations, spending, and remaining amounts per category.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = User::findOrFail($this->userId);
        $period = $user->currentBudgetPeriod();

        if (! $period) {
            return json_encode(['status' => 'No active budget period']);
        }

        $allocations = $period->allocations()->with('category')->get();

        $monthStart = $period->month->startOfMonth();
        $monthEnd = $period->month->endOfMonth();

        $spentByCategory = Transaction::where('user_id', $this->userId)
            ->where('amount', '<', 0)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total_spent')
            ->groupBy('category_id')
            ->pluck('total_spent', 'category_id');

        return json_encode([
            'month' => $period->month->format('F Y'),
            'total_income' => '$'.Money::format($period->total_income),
            'total_allocated' => '$'.Money::format($period->total_allocated),
            'allocations' => $allocations->map(function ($a) use ($spentByCategory) {
                $spent = (int) ($spentByCategory[$a->category_id] ?? 0);

                return [
                    'category' => $a->category?->name ?? 'Unknown',
                    'allocated' => '$'.Money::format($a->allocated_amount),
                    'spent' => '$'.Money::format($spent),
                    'remaining' => '$'.Money::format($a->allocated_amount - $spent),
                    'is_fixed' => $a->is_fixed,
                    'is_locked' => $a->is_locked,
                ];
            })->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
