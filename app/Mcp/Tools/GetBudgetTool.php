<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_budget')]
#[Description('Get current budget status with allocations and spending per category.')]
class GetBudgetTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->string()->description('Month in YYYY-MM format (default: current)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Authentication required.');
        }

        $month = $request->get('month');

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $period = $user->budget?->periods()
                ->whereDate('month', $month.'-01')
                ->first();
        } else {
            $period = $user->currentBudgetPeriod();
        }

        if (! $period) {
            return Response::text('No budget period found.');
        }

        $allocations = $period->allocations()->with('category')->get();

        $result = [
            'month' => $period->month->format('F Y'),
            'total_income' => '$'.Money::format($period->total_income),
            'total_allocated' => '$'.Money::format($period->total_allocated),
            'allocations' => $allocations->map(fn ($a) => [
                'category' => $a->category?->name ?? 'Unknown',
                'allocated' => '$'.Money::format($a->allocated_amount),
                'is_fixed' => $a->is_fixed,
                'is_locked' => $a->is_locked,
            ])->values(),
        ];

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }
}
