<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Models\Debt;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayoffProjector
{
    public function __construct(
        private readonly AvalancheCalculator $calculator,
    ) {}

    /**
     * @param  Collection<int, Debt>  $debts
     */
    public function atCurrentRate(Collection $debts): PayoffSchedule
    {
        return $this->calculator->projectPayoffSchedule($debts, 0);
    }

    /**
     * @param  Collection<int, Debt>  $debts
     */
    public function withExtra(Collection $debts, int $monthlyExtra): PayoffSchedule
    {
        return $this->calculator->projectPayoffSchedule($debts, $monthlyExtra);
    }

    /**
     * @param  Collection<int, Debt>  $debts
     * @param  array<int, int>  $extras
     * @return array<int, array{extra: int, months: int, total_interest: int, debt_free_date: Carbon}>
     */
    public function compareScenarios(Collection $debts, array $extras): array
    {
        $scenarios = [];

        foreach ($extras as $extra) {
            $schedule = $this->calculator->projectPayoffSchedule($debts, $extra);
            $scenarios[] = [
                'extra' => $extra,
                'months' => $schedule->monthsToDebtFree,
                'total_interest' => $schedule->totalInterestPaid,
                'debt_free_date' => $schedule->projectedDebtFreeDate,
            ];
        }

        return $scenarios;
    }

    /**
     * @param  Collection<int, Debt>  $debts
     */
    public function debtFreeDate(Collection $debts, int $monthlyExtra): Carbon
    {
        return $this->calculator->projectPayoffSchedule($debts, $monthlyExtra)->projectedDebtFreeDate;
    }

    /**
     * @param  Collection<int, Debt>  $debts
     */
    public function totalInterestSaved(Collection $debts, int $monthlyExtra): int
    {
        $baseline = $this->calculator->projectPayoffSchedule($debts, 0);
        $withExtra = $this->calculator->projectPayoffSchedule($debts, $monthlyExtra);

        return $baseline->totalInterestPaid - $withExtra->totalInterestPaid;
    }
}
