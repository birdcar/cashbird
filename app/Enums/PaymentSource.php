<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentSource: string
{
    case Detected = 'detected';
    case Manual = 'manual';
}
