<?php

declare(strict_types=1);

namespace App\Services\Debt;

use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class PayoffSchedule
{
    /**
     * @param  Collection<int, array{debt_name: string, payoff_month: int, payoff_date: Carbon, freed_amount: int}>  $milestones
     * @param  Collection<int, array{month: int, debts: array<string, array{balance: int, payment: int, interest: int}>}>  $timeline
     */
    public function __construct(
        public Collection $milestones,
        public int $totalInterestPaid,
        public int $monthsToDebtFree,
        public Carbon $projectedDebtFreeDate,
        public Collection $timeline,
    ) {}
}
