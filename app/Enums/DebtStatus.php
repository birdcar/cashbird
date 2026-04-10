<?php

declare(strict_types=1);

namespace App\Enums;

enum DebtStatus: string
{
    case Active = 'active';
    case PaidOff = 'paid_off';
    case Closed = 'closed';
}
