<?php

declare(strict_types=1);

namespace App\Enums;

enum InsightSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case ActionRequired = 'action_required';
}
