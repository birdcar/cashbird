<?php

declare(strict_types=1);

namespace App\Enums;

enum InsightStatus: string
{
    case Active = 'active';
    case Dismissed = 'dismissed';
    case ActedOn = 'acted_on';
}
