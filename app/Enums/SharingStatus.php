<?php

declare(strict_types=1);

namespace App\Enums;

enum SharingStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
