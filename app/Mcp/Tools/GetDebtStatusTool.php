<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Debt\PayoffProjector;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_debt_status')]
#[Description('Get debt overview with balances, APRs, and payoff projections.')]
class GetDebtStatusTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request, PayoffProjector $projector): Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Authentication required.');
        }

        $debts = $user->debts()->active()->get();

        if ($debts->isEmpty()) {
            return Response::text('No active debts found.');
        }

        $schedule = $projector->atCurrentRate($debts);

        $result = [
            'total_owed' => '$'.Money::format($debts->sum('current_balance')),
            'monthly_minimums' => '$'.Money::format($debts->sum('minimum_payment')),
            'projected_debt_free' => $schedule->projectedDebtFreeDate->format('M Y'),
            'total_interest' => '$'.Money::format($schedule->totalInterestPaid),
            'debts' => $debts->map(fn ($d) => [
                'name' => $d->name,
                'type' => $d->type->value,
                'balance' => '$'.Money::format($d->current_balance),
                'apr' => number_format((float) $d->apr, 2).'%',
                'minimum' => '$'.Money::format($d->minimum_payment),
            ])->values(),
        ];

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }
}
