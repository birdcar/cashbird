<?php

declare(strict_types=1);

namespace App\Enums;

enum SavingsStage: string
{
    case StarterEmergencyFund = 'starter_emergency_fund';
    case DebtPayoff = 'debt_payoff';
    case FullEmergencyFund = 'full_emergency_fund';
    case NamedGoals = 'named_goals';
}
