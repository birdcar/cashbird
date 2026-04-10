<?php

declare(strict_types=1);

namespace App\Support;

class Money
{
    public static function toCents(string|float $dollars): int
    {
        return (int) round((float) $dollars * 100);
    }

    public static function toDollars(int $cents): float
    {
        return $cents / 100;
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
