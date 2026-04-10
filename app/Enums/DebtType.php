<?php

declare(strict_types=1);

namespace App\Enums;

enum DebtType: string
{
    case CreditCard = 'credit_card';
    case PaydayLoan = 'payday_loan';
    case StudentLoan = 'student_loan';
    case PersonalLoan = 'personal_loan';
    case AutoLoan = 'auto_loan';
    case Mortgage = 'mortgage';
    case RecoveryPlan = 'recovery_plan';
}
