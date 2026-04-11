<?php

declare(strict_types=1);

namespace App\Ai\Tools;

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

        return json_encode([
            'month' => $period->month->format('F Y'),
            'total_income' => '$'.Money::format($period->total_income),
            'total_allocated' => '$'.Money::format($period->total_allocated),
            'allocations' => $allocations->map(fn ($a) => [
                'category' => $a->category?->name ?? 'Unknown',
                'allocated' => '$'.Money::format($a->allocated_amount),
                'is_fixed' => $a->is_fixed,
                'is_locked' => $a->is_locked,
            ])->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
