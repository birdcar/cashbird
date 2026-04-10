<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Models\Debt;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvalancheCalculator
{
    private const int MAX_MONTHS = 600;

    /**
     * @param  Collection<int, Debt>  $debts
     * @return Collection<int, Debt>
     */
    public function calculatePayoffOrder(Collection $debts): Collection
    {
        return $debts->sortByDesc('apr')->values();
    }

    /**
     * @param  Collection<int, Debt>  $debts
     * @return array{allocations: array<string, int>, remainder: int}
     */
    public function allocateExtraPayment(Collection $debts, int $extraCents): array
    {
        $sorted = $this->calculatePayoffOrder(
            $debts->filter(fn ($d) => $d->status === 'active' && $d->current_balance > 0)
        );

        $allocations = [];
        $remainder = $extraCents;

        foreach ($sorted as $debt) {
            if ($remainder <= 0) {
                break;
            }

            if ($debt->is_in_recovery) {
                continue;
            }

            $canApply = min($remainder, $debt->current_balance);
            $allocations[$debt->id] = $canApply;
            $remainder -= $canApply;
        }

        return ['allocations' => $allocations, 'remainder' => $remainder];
    }

    /**
     * @param  Collection<int, Debt>  $debts
     */
    public function projectPayoffSchedule(Collection $debts, int $monthlyExtra = 0): PayoffSchedule
    {
        $activeDebts = $debts
            ->filter(fn ($d) => $d->status === 'active' && $d->current_balance > 0)
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'balance' => $d->current_balance,
                'apr' => (float) $d->apr,
                'minimum' => $d->minimum_payment,
                'is_in_recovery' => $d->is_in_recovery,
                'recovery_fixed' => $d->is_in_recovery ? ($d->recovery_terms['fixed_payment'] ?? $d->minimum_payment) : null,
            ])
            ->sortByDesc('apr')
            ->values()
            ->all();

        if (empty($activeDebts)) {
            return new PayoffSchedule(
                milestones: collect(),
                totalInterestPaid: 0,
                monthsToDebtFree: 0,
                projectedDebtFreeDate: Carbon::now(),
                timeline: collect(),
            );
        }

        $milestones = [];
        $timeline = [];
        $totalInterest = 0;
        $extraPool = $monthlyExtra;
        $month = 0;

        while (! empty($activeDebts) && $month < self::MAX_MONTHS) {
            $month++;
            $monthSnapshot = [];

            foreach ($activeDebts as &$debt) {
                $monthlyInterest = (int) round($debt['balance'] * ($debt['apr'] / 12 / 100));
                $totalInterest += $monthlyInterest;

                if ($debt['is_in_recovery']) {
                    $payment = $debt['recovery_fixed'];
                } else {
                    $payment = $debt['minimum'];
                }

                $debt['balance'] = $debt['balance'] + $monthlyInterest - $payment;

                $monthSnapshot[$debt['name']] = [
                    'balance' => max(0, $debt['balance']),
                    'payment' => $payment,
                    'interest' => $monthlyInterest,
                ];
            }
            unset($debt);

            // Apply extra to highest-APR non-recovery debt
            $remaining = $extraPool;
            foreach ($activeDebts as &$debt) {
                if ($remaining <= 0) {
                    break;
                }
                if ($debt['is_in_recovery']) {
                    continue;
                }
                if ($debt['balance'] <= 0) {
                    continue;
                }

                $apply = min($remaining, $debt['balance']);
                $debt['balance'] -= $apply;
                $remaining -= $apply;
                $monthSnapshot[$debt['name']]['payment'] += $apply;
            }
            unset($debt);

            // Check for payoffs and snowball rollup
            $paidOff = [];
            foreach ($activeDebts as $i => $debt) {
                if ($debt['balance'] <= 0) {
                    $milestones[] = [
                        'debt_name' => $debt['name'],
                        'payoff_month' => $month,
                        'payoff_date' => Carbon::now()->addMonths($month),
                        'freed_amount' => $debt['is_in_recovery'] ? $debt['recovery_fixed'] : $debt['minimum'],
                    ];
                    $extraPool += $debt['is_in_recovery'] ? $debt['recovery_fixed'] : $debt['minimum'];
                    $monthSnapshot[$debt['name']]['balance'] = 0;
                    $paidOff[] = $i;
                }
            }

            foreach (array_reverse($paidOff) as $i) {
                array_splice($activeDebts, $i, 1);
            }

            $timeline[] = ['month' => $month, 'debts' => $monthSnapshot];
        }

        return new PayoffSchedule(
            milestones: collect($milestones),
            totalInterestPaid: (int) $totalInterest,
            monthsToDebtFree: $month,
            projectedDebtFreeDate: Carbon::now()->addMonths($month),
            timeline: collect($timeline),
        );
    }
}
