<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\Debt\PayoffProjector;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDebtOverview implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Get all debts with current balances, APRs, minimum payments, and projected payoff dates.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = User::findOrFail($this->userId);
        $debts = $user->debts()->active()->get();

        if ($debts->isEmpty()) {
            return json_encode(['status' => 'No active debts']);
        }

        $projector = app(PayoffProjector::class);
        $schedule = $projector->atCurrentRate($debts);

        return json_encode([
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
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
