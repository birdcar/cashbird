<?php

declare(strict_types=1);

namespace App\Services\WorkOS;

class FGASchema
{
    /** @return array<int, array{type: string, relations?: array<string, array{inherit?: list<string>, allow: list<string>}>}> */
    public static function definition(): array
    {
        return [
            [
                'type' => 'user',
            ],
            [
                'type' => 'budget_category',
                'relations' => [
                    'owner' => ['allow' => ['user']],
                    'editor' => ['inherit' => ['owner'], 'allow' => ['user']],
                    'viewer' => ['inherit' => ['editor'], 'allow' => ['user']],
                ],
            ],
            [
                'type' => 'budget_period',
                'relations' => [
                    'owner' => ['allow' => ['user']],
                    'viewer' => ['inherit' => ['owner'], 'allow' => ['user']],
                ],
            ],
            [
                'type' => 'report',
                'relations' => [
                    'owner' => ['allow' => ['user']],
                    'viewer' => ['inherit' => ['owner'], 'allow' => ['user']],
                ],
            ],
        ];
    }
}
