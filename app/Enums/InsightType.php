<?php

declare(strict_types=1);

namespace App\Enums;

enum InsightType: string
{
    case UnusedSubscription = 'unused_subscription';
    case SpendingSpike = 'spending_spike';
    case SavingsOpportunity = 'savings_opportunity';
    case DebtMilestone = 'debt_milestone';
    case Anomaly = 'anomaly';
}
