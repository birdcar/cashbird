<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Transaction;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSpendingTrends implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Get month-over-month spending trends by category for the last 3 months.';
    }

    public function handle(Request $request): Stringable|string
    {
        $months = [];
        for ($i = 2; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();

            $transactions = Transaction::where('user_id', $this->userId)
                ->where('amount', '<', 0)
                ->whereBetween('date', [$start, $end])
                ->with('category')
                ->get();

            $byCategory = $transactions->groupBy(fn ($t) => $t->category?->name ?? 'Uncategorized')
                ->map(fn ($group) => abs($group->sum('amount')));

            $months[] = [
                'month' => $start->format('M Y'),
                'total' => '$'.Money::format($byCategory->sum()),
                'categories' => $byCategory->map(fn ($amount, $name) => '$'.Money::format($amount)),
            ];
        }

        return json_encode($months);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
