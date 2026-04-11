<?php

declare(strict_types=1);

namespace App\Enums;

enum BudgetCategory: string
{
    case Need = 'need';
    case Want = 'want';
    case Savings = 'savings';
}
